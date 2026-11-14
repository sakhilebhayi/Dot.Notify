<?php

namespace Tests\Feature;

use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The responsive app shell (see README.md, "Responsive shell"): below 860px the sidebar is an
 * off-canvas drawer opened from a labelled Menu button. These tests pin the markup hooks the
 * layout, its script and its CSS depend on, through real authenticated GETs. They do not (and
 * cannot) check how it looks; that was checked in a browser.
 */
class ResponsiveShellTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create(['name' => 'Shell Tester']);
    }

    /** @return array<string, array{string}> */
    public static function shellPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'connected apps' => ['/connected-apps'],
            'profile' => ['/user/profile'],
        ];
    }

    #[DataProvider('shellPages')]
    public function test_every_page_in_the_layout_has_the_viewport_meta_and_the_drawer_hooks(string $url): void
    {
        $response = $this->actingAs($this->user)->get($url)->assertOk();

        $response->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1" />', false);

        // R-52: the shared header's menu button (44px, labelled) controls the sidebar, closed by default.
        $response->assertSeeInOrder([
            '<header',
            'data-dh',
            'data-dh-menu-button',
            'aria-expanded="false"',
            'aria-controls="shell-drawer"',
            'aria-label="Menu"',
        ], false);

        // The phone menu is the existing sidebar, a labelled region, with a close button the header script knows.
        $response->assertSee('<aside class="sidebar" id="shell-drawer" aria-label="Sidebar"', false);
        $response->assertSee('data-dh-close', false);
        $response->assertSee('aria-label="Close menu"', false);

        // The old hand-written drawer machinery (its button, backdrop, inline script) is gone: dot-nav.js owns the behaviour.
        $response->assertDontSee('id="shell-menu-button"', false);
        $response->assertDontSee('id="shell-backdrop"', false);
        $response->assertDontSee('data-shell-behind', false);
        $response->assertSee('<div class="content-wrap" id="main"', false);
    }

    #[DataProvider('shellPages')]
    public function test_the_layout_css_keeps_the_breakpoint_and_the_header_offset_in_one_place(string $url): void
    {
        $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();

        // The page's styles are in the stylesheet the layout loads through Vite.
        $html .= file_get_contents(resource_path('css/notify.css'));

        // The shell breaks where the shared header does (dot-ui --dot-bp-md, 860px), so the menu button and the sidebar never disagree.
        $this->assertStringContainsString('@media (max-width: 860px)', $html);
        $this->assertStringNotContainsString('859.98px', $html);
        $this->assertStringContainsString('.sidebar[data-dh-open]', $html);

        // The sidebar and the content sit under the header by the shared offset token, so they cannot drift apart.
        $this->assertStringContainsString('top:var(--dot-header-offset, 56px)', $html);
        $this->assertStringContainsString('--topbar-h: var(--dot-header-h, 56px)', $html);

        // Wide data scrolls inside .table-scroll, never the page.
        $this->assertStringContainsString('.table-scroll { max-width:100%; overflow-x:auto;', $html);

        // Motion is switched off for people who ask for that.
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
    }

    public function test_the_drawer_behaviour_is_the_shared_script_and_not_an_inline_one(): void
    {
        $html = $this->actingAs($this->user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(asset('dot-ui/js/dot-nav.js'), $html);

        // The shared script handles Escape, the scrim, focus return and the scroll lock for the adopted sidebar.
        $js = (string) file_get_contents(public_path('dot-ui/js/dot-nav.js'));
        $this->assertStringContainsString("'Escape'", $js);
        $this->assertStringContainsString('data-dh-open', $js);
        $this->assertStringContainsString('Lock.lock()', $js);
    }

    public function test_the_legacy_jetstream_navigation_bar_is_no_longer_rendered_in_the_layout(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            // Its root element and its hamburger/responsive menu were hidden behind the fixed bars but still took 64px of flow.
            ->assertDontSee('bg-white dark:bg-gray-800 border-b border-gray-100', false)
            ->assertDontSee('Responsive Navigation Menu', false);
    }

    public function test_the_drawer_carries_the_account_links_the_old_nav_bar_held(): void
    {
        $second = Team::factory()->create(['user_id' => $this->user->id, 'personal_team' => false, 'name' => 'Zebra Logistics Ltd']);

        $response = $this->actingAs($this->user->fresh())->get('/dashboard')->assertOk();

        $html = $response->getContent();
        $sidebar = substr($html, strpos($html, '<aside class="sidebar"'), strpos($html, '</aside>') - strpos($html, '<aside class="sidebar"'));

        // The phone menu (the sidebar) holds the account: profile, team settings, create team, the team switch, the theme and log out.
        foreach (['Profile &amp; Settings', 'Team settings', 'Create team', 'Switch team', 'Zebra Logistics Ltd', 'data-dh-theme-set="dark"', 'Log out'] as $needle) {
            $this->assertStringContainsString($needle, $sidebar);
        }
        $this->assertStringContainsString('action="'.route('current-team.update').'"', $sidebar);
        $this->assertStringContainsString('name="team_id" value="'.$second->id.'"', $sidebar);
        $this->assertStringContainsString('action="'.route('logout').'"', $sidebar);

        // From 860px the header's account menu holds the same links and the sign-out.
        $response->assertSee('data-dh-account', false);
        $response->assertSee('Sign out');
    }

    public function test_a_single_team_user_gets_no_switcher(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Switch team');
    }

    public function test_the_dashboard_lists_wrap_inside_their_cards_and_the_bell_has_a_name(): void
    {
        NotifyLog::create([
            'team_id' => $this->user->currentTeam->id,
            'recipient' => 'wrap-check-recipient@example.test',
            'subject' => 'Wrap check subject',
            'body_preview' => 'b',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard')->assertOk();

        $response->assertSee('class="nf-today"', false);
        $response->assertSee('class="nf-lower"', false);
        $response->assertSeeInOrder(['<table class="nf-table"', 'class="row-break nf-row-title"', 'wrap-check-recipient@example.test'], false);
        $response->assertSee('aria-label="Notifications"', false);
        // The theme is chosen in the account menu now (Light, Dark, System), not by a separate top bar button.
        $response->assertDontSee('aria-label="Toggle dark mode"', false);
        $response->assertSee('data-dh-theme-set="system"', false);
    }

    public function test_connected_apps_rows_stack_below_the_sidebar_breakpoint_not_only_on_phones(): void
    {
        $response = $this->actingAs($this->user)->get('/connected-apps')->assertOk();

        // Its styles moved from an inline <style> (a nonce CSP blocks it) into the notify stylesheet.
        $response->assertDontSee('<style>', false);
        $css = (string) file_get_contents(resource_path('css/notify.css'));
        $this->assertStringContainsString('.ca-row { display:grid', $css);
        $this->assertStringContainsString('@media (max-width: 1099.98px)', $css);
        $this->assertStringContainsString('.ca-switch { position:relative; width:44px; height:44px;', $css, 'the switch is a 44px touch target');
        $response->assertSee('class="page"', false);
    }
}

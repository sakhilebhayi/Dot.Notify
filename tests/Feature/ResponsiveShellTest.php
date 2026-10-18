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

        // Labelled menu button that controls the drawer, closed by default.
        $response->assertSeeInOrder([
            '<button type="button" id="shell-menu-button"',
            'aria-expanded="false"',
            'aria-controls="shell-drawer"',
            '<span>Menu</span>',
        ], false);

        // The drawer is the existing sidebar, a labelled region.
        $response->assertSee('<aside class="sidebar" id="shell-drawer" aria-label="Sidebar"', false);
        $response->assertSee('id="shell-backdrop"', false);
        $response->assertSee('id="shell-close"', false);
        $response->assertSee('aria-label="Close menu"', false);

        // The top bar and the content wrapper are what goes inert behind an open drawer.
        $response->assertSee('<header class="topbar" data-shell-behind>', false);
        $response->assertSee('<div class="content-wrap" data-shell-behind>', false);
    }

    #[DataProvider('shellPages')]
    public function test_the_layout_css_keeps_the_breakpoint_and_the_top_bar_offset_in_one_place(string $url): void
    {
        $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('@media (max-width: 859.98px)', $html);
        $this->assertStringContainsString('(min-width: 860px)', $html);

        // Content is offset by the top bar's own height token, so they cannot drift apart.
        $this->assertStringContainsString('height:var(--topbar-h)', $html);
        $this->assertStringContainsString('padding-top:var(--topbar-h)', $html);

        // Wide data scrolls inside .table-scroll, never the page.
        $this->assertStringContainsString('.table-scroll { max-width:100%; overflow-x:auto;', $html);

        // Motion is switched off for people who ask for that.
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
    }

    public function test_the_drawer_script_handles_escape_the_backdrop_and_focus_return(): void
    {
        $html = $this->actingAs($this->user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString("event.key === 'Escape'", $html);
        $this->assertStringContainsString("backdrop.addEventListener('click'", $html);
        $this->assertStringContainsString("el.setAttribute('inert', '')", $html);
        $this->assertStringContainsString('button.focus()', $html);
        $this->assertStringContainsString("'aria-expanded', 'true'", $html);
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

        $response->assertSeeInOrder(['Team settings', 'Create team', 'Switch team', 'Zebra Logistics Ltd', 'Log out'], false);
        $response->assertSee('action="'.route('current-team.update').'"', false);
        $response->assertSee('name="team_id" value="'.$second->id.'"', false);
        $response->assertSee('action="'.route('logout').'"', false);
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

        $response->assertSee('class="kpi-grid"', false);
        $response->assertSee('class="two-col"', false);
        $response->assertSeeInOrder(['<div class="dot-row">', 'wrap-check-recipient@example.test'], false);
        $response->assertSee('aria-label="Notifications"', false);
        $response->assertSee('aria-label="Toggle dark mode"', false);
    }

    public function test_connected_apps_rows_stack_below_the_sidebar_breakpoint_not_only_on_phones(): void
    {
        $this->actingAs($this->user)->get('/connected-apps')
            ->assertOk()
            ->assertSee('@media (max-width: 1099.98px)', false)
            ->assertSee('class="page"', false);
    }
}

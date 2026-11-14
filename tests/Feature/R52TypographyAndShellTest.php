<?php

namespace Tests\Feature;

use App\Models\NotifyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-52 on Dot.Notify: Roboto from the shared dot-ui is the typeface of everything, nothing is loaded from another host, the Tailwind
 * Play CDN is gone (the built stylesheet replaces it), the shell carries the shared header, and the log keeps every column on a phone.
 */
class R52TypographyAndShellTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create(['name' => 'Type Tester']);
    }

    /** @return array<string, array{string}> */
    public static function signedInPages(): array
    {
        return ['dashboard' => ['/dashboard'], 'connected apps' => ['/connected-apps'], 'profile' => ['/user/profile'], 'search' => ['/search?q=ab']];
    }

    // ------------------------------------------------------------------ Roboto

    #[DataProvider('signedInPages')]
    public function test_roboto_and_the_type_scale_come_from_this_site_on_every_signed_in_page(string $url): void
    {
        $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*\bclass="dot-roboto"/', $html);
        $this->assertStringContainsString('href="'.asset('dot-ui/css/dot-roboto.css').'"', $html);
        $this->assertStringContainsString('href="'.asset('dot-ui/css/dot-type.css').'"', $html);
        $this->assertStringContainsString('href="'.asset('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2').'" as="font" type="font/woff2" crossorigin', $html);

        foreach (['roboto-latin-wght-normal.woff2', 'roboto-latin-ext-wght-normal.woff2', 'OFL.txt'] as $file) {
            $this->assertFileExists(public_path('dot-ui/fonts/roboto/'.$file));
        }

        $face = (string) file_get_contents(public_path('dot-ui/css/dot-roboto.css'));
        $this->assertStringContainsString("font-family: 'Roboto'", $face);
    }

    public function test_the_notify_stylesheet_has_no_font_face_and_maps_its_tokens_to_the_shared_scale(): void
    {
        $css = (string) file_get_contents(resource_path('css/notify.css'));

        $this->assertStringNotContainsString('@font-face', $css);
        $this->assertStringNotContainsStringIgnoringCase('atkinson', $css);
        $this->assertStringContainsString('--font: var(--dot-font-sans);', $css);
        $this->assertStringContainsString('--font-code: var(--dot-font-mono);', $css, 'code and event names keep the shared monospace');
        $this->assertStringContainsString('font-size: var(--dot-text-body-size);', $css);
        $this->assertStringNotContainsString('font-weight:750', $css, 'a weight Roboto draws as 700 anyway: say 700');
        $this->assertDirectoryDoesNotExist(resource_path('fonts'), 'the old typeface files are removed, not left dead');
    }

    public function test_no_font_or_script_is_loaded_from_another_host_anywhere_in_the_app(): void
    {
        $files = array_merge(
            glob(resource_path('views/*.php')) ?: [],
            glob(resource_path('views/*/*.php')) ?: [],
            glob(resource_path('views/*/*/*.php')) ?: [],
            glob(resource_path('css/*.css')) ?: [],
            glob(resource_path('js/*.js')) ?: [],
            glob(public_path('js/*.js')) ?: [],
        );
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $text = (string) file_get_contents($file);
            foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.tailwindcss.com', 'cdnjs.cloudflare.com', 'jsdelivr.net', 'unpkg.com', 'bunny.net', 'typekit'] as $host) {
                $this->assertStringNotContainsString($host, $text, basename($file).' names '.$host);
            }
        }

        // The icon font that used to come from Google is gone too (the platform tiles on the error page print a letter).
        $this->assertStringNotContainsString('material-symbols', (string) file_get_contents(resource_path('views/errors/_ecosystem-layout.blade.php')));
    }

    public function test_the_tailwind_play_cdn_is_replaced_by_the_built_stylesheet_with_roboto(): void
    {
        $html = $this->actingAs($this->user)->get('/user/profile')->assertOk()->getContent();

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('tailwind.config', $html);
        // (The tests run without Vite's tags, so the layout source is what is checked.) It follows the notify stylesheet, as the
        // CDN's injected style did, so the pad keeps its own base.
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString("@vite(['resources/css/jetstream.css'])", $layout);
        $this->assertLessThan(strpos($layout, "@vite(['resources/css/jetstream.css'])"), strpos($layout, "@vite(['resources/css/notify.css'])"));

        $config = (string) file_get_contents(base_path('tailwind.config.js'));
        $this->assertStringNotContainsString('Figtree', $config);
        $this->assertStringContainsString('dot-ui/tailwind/tailwind-preset.cjs', $config);
        $this->assertStringContainsString('preflight: false', (string) file_get_contents(base_path('tailwind.jetstream.config.js')));

        // The preset maps font-sans to the shared variable, so a Jetstream control cannot fall back to Arial.
        $this->assertStringContainsString("sans: ['var(--dot-font-sans)']", (string) file_get_contents(base_path('dot-ui/tailwind/tailwind-preset.cjs')));
        $this->assertStringContainsString('plugins: []', (string) file_get_contents(base_path('tailwind.jetstream.config.js')));
        $this->assertStringContainsString('preflight: false', (string) file_get_contents(base_path('tailwind.jetstream.config.js')));
    }

    public function test_the_built_jetstream_stylesheet_has_roboto_and_no_reset_or_forms_plugin(): void
    {
        // CI runs without an asset build (the suite fakes Vite); the deploy builds, and this runs wherever `npm run build` has.
        $built = collect(glob(public_path('build/assets/jetstream-*.css')))->first();
        if ($built === null) {
            $this->markTestSkipped('run npm run build first');
        }

        $builtCss = (string) file_get_contents($built);
        $this->assertStringContainsString('var(--dot-font-sans)', $builtCss);
        // No preflight and no forms plugin: the pad's own controls (the log's status select) keep their native look, as under the CDN build.
        $this->assertStringNotContainsString('appearance:none', $builtCss);
        $this->assertStringNotContainsString('button,[type=button]', $builtCss);
    }

    public function test_the_vite_inputs_are_the_ones_the_views_ask_for(): void
    {
        $config = (string) file_get_contents(base_path('vite.config.js'));
        $views = collect(array_merge(glob(resource_path('views/*.php')), glob(resource_path('views/*/*.php'))))->map(fn ($f) => (string) file_get_contents($f))->implode("\n");

        preg_match_all('#resources/(?:css|js)/[a-z.]+#', $views, $m);
        foreach (array_unique($m[0]) as $input) {
            $this->assertStringContainsString("'".$input."'", $config, $input.' is asked for by a view but is not a Vite input (the deploy would fail on a missing manifest entry)');
        }
    }

    // ------------------------------------------------------------------ no inline script or style (a nonce CSP)

    #[DataProvider('signedInPages')]
    public function test_the_signed_in_shell_has_no_inline_script_or_style_of_its_own(string $url): void
    {
        $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();

        preg_match_all('#<script\b([^>]*)>(.*?)</script>#s', $html, $scripts, PREG_SET_ORDER);
        foreach ($scripts as [$all, $attrs, $body]) {
            if (! str_contains($attrs, ' src=') && trim($body) !== '') {
                // The only inline scripts are Livewire's own (its boot data and navigate guard), which are not ours to nonce here.
                $this->assertMatchesRegularExpression('/livewire|Livewire/', $all, 'an inline script that is not Livewire\'s: '.substr(trim($body), 0, 80));
            }
        }

        preg_match_all('#<style\b[^>]*>(.*?)</style>#s', $html, $styles);
        foreach ($styles[1] as $css) {
            $this->assertStringContainsString('wire\\:', $css, 'an inline <style> that is not Livewire\'s');
        }

        // The header and the sidebar (the shell itself) carry no style attribute. (Jetstream's own banner and modals use Alpine's display:none.)
        $shell = substr($html, strpos($html, '<header'), strpos($html, '</aside>') - strpos($html, '<header'));
        $this->assertStringNotContainsString(' style="', $shell, 'no style attribute in the shell');
        $this->assertStringContainsString('src="'.asset('js/notify-shell.js').'"', $html, 'the theme script is a file');
    }

    public function test_the_theme_script_maps_the_shared_theme_choice_to_the_pads_dark_class(): void
    {
        $js = (string) file_get_contents(public_path('js/notify-shell.js'));

        $this->assertStringContainsString("'dot-theme'", $js);
        $this->assertStringContainsString("'dot:theme'", $js);
        $this->assertStringContainsString("classList.toggle('dark'", $js);
        $this->assertStringContainsString('prefers-color-scheme: dark', $js);
        $this->assertFileDoesNotExist(public_path('js/app.js'));
    }

    // ------------------------------------------------------------------ the shared header

    public function test_the_header_carries_the_logo_the_search_the_switcher_the_bell_and_the_account(): void
    {
        $html = $this->actingAs($this->user)->get('/dashboard')->assertOk()->getContent();
        $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));

        $this->assertStringContainsString('class="dh nf-header"', $header);
        $this->assertStringContainsString('aria-label="Dot.Notify home"', $header);
        $this->assertStringContainsString('src="'.asset('images/mark-light.png').'"', $header, 'the original logo file, not redrawn');
        $this->assertStringContainsString('data-dh-search', $header);
        $this->assertStringContainsString('data-dsw', $header);
        $this->assertStringContainsString('nf-bell-btn', $header);
        $this->assertStringContainsString('data-dh-account', $header);
        $this->assertStringContainsString('Sign out', $header);
        $this->assertStringContainsString('data-dh-theme-set="dark"', $header);
        $this->assertStringContainsString('href="#main"', $header, 'a skip link');

        // One menu button for phones (it opens the sidebar) and no second top bar.
        $this->assertSame(1, substr_count($html, 'data-dh-menu-button'));
        $this->assertSame(0, substr_count($html, 'class="topbar'));
        // The title no longer rides in the bar, so it cannot be cut to "Das…": it is the page's own h1 and the document title.
        $this->assertStringContainsString('<title>Dashboard · Dot.Notify</title>', $html);
    }

    public function test_a_jetstream_page_keeps_its_name_as_a_heading_now_that_the_bar_no_longer_carries_it(): void
    {
        $this->actingAs($this->user)->get('/user/profile')->assertOk()->assertSee('class="nf-pagehead"', false)->assertSeeInOrder(['nf-pagehead', 'Profile'], false);
        // The pages with their own h1 print no second heading.
        $this->actingAs($this->user)->get('/dashboard')->assertOk()->assertDontSee('nf-pagehead', false);
        $this->actingAs($this->user)->get('/connected-apps')->assertOk()->assertDontSee('nf-pagehead', false);
    }

    public function test_the_header_search_is_reachable_by_keyboard_and_the_sidebar_has_a_close_button(): void
    {
        $html = $this->actingAs($this->user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-dsr-shortcut', $html, 'Ctrl K focuses it');
        $this->assertStringContainsString('data-dsr-escape-clear', $html, 'Escape empties it, as InfoDot\'s does');
        $this->assertStringContainsString('aria-label="Search Dot.Notify"', $html);
        $this->assertStringContainsString('aria-label="Close menu"', $html);
        $this->assertStringContainsString('<script src="'.asset('dot-ui/js/dot-nav.js').'" defer></script>', $html);
        $this->assertStringContainsString('<script src="'.asset('dot-ui/js/dot-search.js').'" defer></script>', $html);
    }

    public function test_the_shared_copy_is_the_version_the_manifest_names(): void
    {
        $lines = file(base_path('dot-ui/VERSION'), FILE_IGNORE_NEW_LINES);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $lines[0]);
        $this->assertSame('0.5.0', $lines[1]);
        $this->assertFileExists(base_path('dot-ui/MANIFEST.sha256'));
        $this->assertFileExists(resource_path('views/components/dot/header.blade.php'));
        $this->assertFileExists(public_path('dot-ui/js/dot-nav.js'));
    }

    // ------------------------------------------------------------------ the log keeps every column on a phone

    public function test_the_log_keeps_the_event_and_when_columns_instead_of_hiding_them(): void
    {
        NotifyLog::create(['team_id' => $this->user->currentTeam->id, 'recipient' => 'col@example.test', 'subject' => 'Columns', 'body_preview' => 'x', 'status' => 'sent', 'event' => 'finance.invoice.overdue', 'sent_at' => now()]);

        $html = $this->actingAs($this->user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('<th scope="col">Event</th>', $html);
        $this->assertStringContainsString('<th scope="col">When</th>', $html);
        $this->assertStringContainsString('finance.invoice.overdue', $html);
        $this->assertStringNotContainsString('nf-hide-sm', $html);
        $this->assertStringNotContainsString('nf-hide-sm', (string) file_get_contents(resource_path('css/notify.css')));

        // Below 860px each message is a stacked row, not a dropped column; table roles are restated because display changes them.
        $css = (string) file_get_contents(resource_path('css/notify.css'));
        $this->assertStringContainsString("grid-template-areas:'main status' 'event when'", $css);
        $this->assertStringContainsString('role="row"', $html);
        $this->assertStringContainsString('role="cell"', $html);
    }

    // ------------------------------------------------------------------ guest pages

    public function test_the_sign_in_page_is_roboto_self_hosted_and_in_the_pads_colours(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*\bclass="dark dot-roboto"/', $html);
        $this->assertStringContainsString(asset('dot-ui/css/dot-roboto.css'), $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        preg_match_all('#<style\b[^>]*>(.*?)</style>#s', $html, $styles);
        foreach ($styles[1] as $inline) {
            $this->assertStringContainsString('wire\\:', $inline, 'an inline <style> that is not Livewire\'s');
        }
        $this->assertStringContainsString("'resources/css/guest.css'", (string) file_get_contents(resource_path('views/layouts/guest.blade.php')));

        $css = (string) file_get_contents(resource_path('css/guest.css'));
        $this->assertStringContainsString('--ink: #0d1130;', $css);
        $this->assertStringNotContainsString('Sora', $css);
        $this->assertStringNotContainsString('#8bc34a', $css, 'the old green is gone');
    }
}

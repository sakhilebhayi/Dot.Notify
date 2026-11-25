<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use App\Services\InfoDot\EntitlementClient;
use App\Support\EntitlementRefusal;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in area honours the company's entitlement (R-50) and InfoDot's switch for the platform (R-61).
 *
 * InfoDot decides whether a company holds Dot.Notify, and whether the platform is available at all. A person who is
 * already signed in must stop working when either changes, so each request in the signed-in area asks (through a
 * one-minute cache, see EntitlementClient) about the current company. A clear "no" answers with the plain refusal
 * page and HTTP 403 (a bare 403 on a Livewire update or broadcasting auth): nothing is read or changed. A platform
 * InfoDot has switched off answers 503 with Retry-After 60 (the plain "not available right now" page, or JSON for
 * JSON and Livewire requests); nothing is read or changed either. Anything else, including every kind of failure with
 * no earlier answer on record, lets the request through exactly as before.
 *
 * It sits on the signed-in route group (routes/web.php), on Jetstream's own pages (config/jetstream.php), on
 * /broadcasting/auth (EnsureCompanyEntitledForBroadcast) and is a Livewire persistent middleware. It is not on
 * sign-in, sign-out, the handoff receiver, health checks, the inbound webhook receiver or the ingest API: those
 * are machine-to-machine, so queued and incoming messages are never held back by it.
 */
class EnsureCompanyEntitled
{
    /** Routes inside the checked groups that must keep working for a person whose company is refused. */
    private const EXEMPT = [
        'logout',              // leaving
        'current-team.update', // switching to another company
        'profile.show',        // the person's own account
        'terms.show', 'policy.show', 'team-invitations.accept',
    ];

    /** Session key: redeem's `entitled` for the person's personal workspace, remembered at sign-in (absent: not set). */
    public const PERSONAL = 'infodot.personal_entitled';

    public function __construct(private readonly EntitlementClient $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->entitlements->enabled() || $request->routeIs(...self::EXEMPT)) {
            return $next($request);
        }

        $user = $request->user() ?? Auth::guard('web')->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $team = $this->company($request, $user);

        // A personal workspace, or a team InfoDot does not know: the read endpoint is never asked. What InfoDot said
        // about the person's personal workspace at sign-in decides: exactly false is a refusal (individuals hold
        // the free core only); true, or nothing remembered, carries on.
        if (! $team) {
            return $next($request);
        }

        if ($team->personal_team || blank($team->infodot_team_uuid)) {
            if (! $request->hasSession() || $request->session()->get(self::PERSONAL) !== false) {
                return $next($request);
            }

            return $this->refuse($request, fn () => EntitlementRefusal::response(
                'company_required',
                null,
                $user->allTeams()->reject(fn ($other) => $other->id === $team->id || $other->personal_team || blank($other->infodot_team_uuid)),
            ));
        }

        $answer = $this->entitlements->forCompany((string) $team->infodot_team_uuid);

        // R-61: a platform InfoDot has switched off (maintenance, unavailable) is off for everyone, whatever they hold.
        // Nothing is deleted or changed; the page says so plainly. Checked before what the company holds, because it is
        // a different question. An older InfoDot gives no `available`, which the client reads as available.
        if ($answer !== null && ! $answer['available']) {
            return $this->unavailable($request, $answer['message']);
        }

        if ($answer === null || $answer['entitled'] === true) {
            return $next($request);
        }

        return $this->refuse($request, fn () => EntitlementRefusal::response(
            $answer['status'],
            $team->name,
            $user->allTeams()->reject(fn ($other) => $other->id === $team->id),
        ));
    }

    /**
     * 503 "temporarily unavailable" (Retry-After 60). JSON for API-style and Livewire requests, the plain page otherwise.
     * Livewire throws away a persistent middleware's non-redirect return, so there the response is thrown instead.
     */
    private function unavailable(Request $request, ?string $message): Response
    {
        $livewire = $request->headers->has('X-Livewire') || request()->headers->has('X-Livewire');

        if ($livewire) {
            throw new HttpResponseException(EntitlementRefusal::unavailableJson($message));
        }

        return $request->expectsJson() ? EntitlementRefusal::unavailableJson($message) : EntitlementRefusal::unavailable($message);
    }

    /**
     * Livewire throws away any non-redirect response a persistent middleware returns, so a refusal page would let a
     * component update run. On a Livewire update the refusal must abort: nothing runs and no component comes back.
     */
    private function refuse(Request $request, Closure $page): Response
    {
        if ($request->headers->has('X-Livewire') || request()->headers->has('X-Livewire')) {
            abort(403);
        }

        return $page();
    }

    /** The company this request works in: the one in the address when the person belongs to it, else their current one. */
    private function company(Request $request, User $user): ?Team
    {
        $named = $request->route('team');

        if (is_scalar($named)) {
            $named = $user->allTeams()->firstWhere('id', (int) $named);
        }

        if ($named instanceof Team && $user->belongsToTeam($named)) {
            return $named;
        }

        $current = $user->currentTeam;

        return $current instanceof Team ? $current : null;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Notify\EventIngestor;
use App\Services\Notify\IngestSignature;
use App\Services\Notify\UnknownChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /api/ingest/events: a Dot app tells Notify "this happened to this
 * company, tell this person". Server to server; no user session.
 *
 * Authentication: the calling platform signs "{timestamp}.{raw body}" with its
 * own secret (config notify.ingest.secrets, from NOTIFY_INGEST_SECRET_<KEY>);
 * see IngestSignature. Unknown platform, blank secret, bad or stale signature
 * all return the same generic 401.
 *
 * Responses: 202 {id, status} for a new event, 200 {id, status} for a replay of
 * the same (company, platform, idempotency_key), 404 for an unknown company,
 * 422 for a bad payload or channel, 429 when the platform+company rate limit
 * is hit. status is routed (queued for delivery), no_rule (company has no
 * active rule for the event: nothing sent, and no rule is ever created here),
 * ignored (rule rate ceiling) or failed (rule unusable).
 */
class IngestEventController extends Controller
{
    private const EVENT_NAME = '/^[A-Za-z0-9][A-Za-z0-9._:\-]*$/';

    public function __invoke(Request $request, EventIngestor $ingestor): JsonResponse
    {
        $raw = $request->getContent();

        if (strlen($raw) > (int) config('notify.ingest.max_body_bytes', 65536)) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }

        $decoded = json_decode($raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        $platform = is_string($decoded['platform'] ?? null) ? strtolower($decoded['platform']) : '';
        $secret = (string) (config('notify.ingest.secrets')[$platform] ?? '');

        if (! IngestSignature::valid($request, $secret)) {
            return response()->json(['error' => 'invalid_request'], 401);
        }

        $validator = Validator::make($decoded, [
            'platform' => ['required', 'string', 'max:40'],
            'company_uuid' => ['required', 'uuid'],
            'event' => ['required', 'string', 'max:150', 'regex:'.self::EVENT_NAME],
            'recipient' => ['required', 'array'],
            'recipient.email' => ['nullable', 'string', 'email', 'max:254'],
            'recipient.user_email' => ['nullable', 'string', 'email', 'max:254'],
            'data' => ['nullable', 'array'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'channel' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (! (is_int($value) || (is_string($value) && mb_strlen($value) <= 100))) {
                    $fail('The channel must be a channel name or id.');
                }
            }],
            'sensitive' => ['nullable', 'array', 'max:50'],
            'sensitive.*' => ['string', 'max:100'],
            'notification_type' => ['nullable', Rule::in(['marketing', 'transactional', 'security', 'product_updates'])],
        ]);

        $validator->after(function ($validator) use ($decoded) {
            if (empty($decoded['recipient']['email'] ?? null) && empty($decoded['recipient']['user_email'] ?? null)) {
                $validator->errors()->add('recipient', 'A recipient email or user_email is required.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['error' => 'invalid_request', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['platform'] = $platform;

        $limitKey = "notify-ingest:{$platform}:{$data['company_uuid']}";
        if (RateLimiter::tooManyAttempts($limitKey, (int) config('notify.ingest.rate_limit_per_minute', 120))) {
            return response()->json(['error' => 'rate_limited'], 429, ['Retry-After' => RateLimiter::availableIn($limitKey)]);
        }
        RateLimiter::hit($limitKey, 60);

        $team = Team::where('infodot_team_uuid', $data['company_uuid'])->first();

        if (! $team) {
            return response()->json(['error' => 'not_found'], 404);
        }

        try {
            [$event, $replay] = $ingestor->ingest($team, $data);
        } catch (UnknownChannel) {
            return response()->json(['error' => 'unknown_channel'], 422);
        }

        return response()->json(['id' => $event->id, 'status' => $event->status], $replay ? 200 : 202);
    }
}

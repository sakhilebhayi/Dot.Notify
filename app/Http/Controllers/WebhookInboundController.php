<?php

namespace App\Http\Controllers;

use App\Models\NotifyInboundEvent;
use App\Models\NotifyLog;
use App\Models\NotifyRule;
use App\Models\NotifyWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives inbound HTTP webhooks from third-party systems (Stripe, GitHub,
 * etc.) at POST /webhooks/{token}. Deliberately NOT behind auth:sanctum or
 * the session guard — the caller is an external system, not a logged-in
 * user — so this route sits outside the app's normal authenticated route
 * group and is rate-limited instead (see routes/web.php).
 *
 * Authentication here is two-factor by design:
 *   1. {token} identifies *which* NotifyWebhook (App\Models\NotifyWebhook::endpoint_token).
 *   2. The `X-Dot-Signature` header proves the caller holds that webhook's
 *      `signing_secret`: hash_hmac('sha256', $rawBody, $signing_secret),
 *      verified with hash_equals() (timing-safe, never `==`).
 *
 * Both an unknown token and a known token with a bad/missing signature
 * return the exact same generic 401 — deliberately, so a caller can never
 * use the response to tell "this token doesn't exist" apart from "this
 * token exists but you don't have its secret" (see acid_test_generic_401
 * pattern verified in tests/Feature/Notify/WebhookInboundTest.php).
 */
class WebhookInboundController extends Controller
{
    public function receive(Request $request, string $token): JsonResponse
    {
        $webhook = NotifyWebhook::where('endpoint_token', $token)->first();

        // Always derive *some* secret and always run hash_hmac + hash_equals,
        // even for an unknown token, so an unknown-token request costs
        // roughly the same as a known-token-bad-signature request rather
        // than short-circuiting immediately on the DB miss.
        $secret            = $webhook->signing_secret ?? hash('sha256', 'unknown-webhook-token:' . $token);
        $rawBody            = $request->getContent();
        $providedSignature = (string) $request->header('X-Dot-Signature', '');
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        $verified = $webhook !== null
            && $webhook->is_active
            && $providedSignature !== ''
            && hash_equals($expectedSignature, $providedSignature);

        if (! $verified) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $payload = json_decode($rawBody, true);
        $payload = is_array($payload) ? $payload : [];

        $sourceEvent  = $payload['event'] ?? $payload['type'] ?? null;
        $triggerEvent = $webhook->resolveTriggerEvent($sourceEvent);

        $webhook->forceFill(['last_received_at' => now()])->save();

        $inboundEvent = NotifyInboundEvent::create([
            'team_id'           => $webhook->team_id,
            'notify_webhook_id' => $webhook->id,
            'payload'           => $payload,
            'source_event'      => $sourceEvent,
            'trigger_event'     => $triggerEvent,
            'verified_at'       => now(),
            'status'            => 'received',
        ]);

        if ($triggerEvent === null) {
            $inboundEvent->update([
                'status' => 'ignored',
                'note'   => 'No event_map entry for source event "' . ($sourceEvent ?? 'null') . '".',
            ]);

            return response()->json(['status' => 'received'], 200);
        }

        // Route into the existing send pipeline: a NotifyRule maps a
        // trigger_event to a template + channel; a NotifyLog is the same
        // per-recipient audit record NotifyBatch/manual sends create today
        // (see wiki.md §3-4). This pass creates the log in "queued" status,
        // matching NotifyLog's documented status lifecycle — actually
        // dispatching it to a channel driver is the pre-existing send
        // pipeline gap noted in wiki.md, not something introduced here.
        $rule = NotifyRule::where('team_id', $webhook->team_id)
            ->where('trigger_event', $triggerEvent)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            $inboundEvent->update([
                'status' => 'ignored',
                'note'   => "No active NotifyRule for trigger_event \"{$triggerEvent}\".",
            ]);

            return response()->json(['status' => 'received'], 200);
        }

        $template  = $rule->template;
        $recipient = $payload['recipient'] ?? $payload['email'] ?? "webhook:{$webhook->id}";

        $log = NotifyLog::create([
            'team_id'      => $webhook->team_id,
            'channel_id'   => $rule->channel_id,
            'template_id'  => $rule->template_id,
            'recipient'    => is_string($recipient) ? $recipient : "webhook:{$webhook->id}",
            'subject'      => $template?->subject,
            'body_preview' => $template ? $template->render(array_filter($payload, 'is_scalar')) : null,
            'status'       => 'queued',
        ]);

        $inboundEvent->update([
            'status'        => 'routed',
            'notify_log_id' => $log->id,
        ]);

        return response()->json(['status' => 'received'], 200);
    }
}

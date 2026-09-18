<?php

declare(strict_types=1);

namespace App\Billing;

use App\Entitlements\LearnerSubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class RazorpayWebhookProcessor
{
    public function __construct(private LearnerSubscriptionService $subscriptions) {}

    public function process(string $rawBody, string $signature, ?string $eventId): void
    {
        if (strlen($rawBody) > 1_048_576) {
            throw new InvalidPaymentWebhook('Razorpay webhook payload is too large.');
        }
        $secret = config('billing.razorpay.webhook_secret');
        if (! is_string($secret) || strlen($secret) < 16) {
            throw new InvalidPaymentWebhook('Razorpay webhook secret is unavailable.');
        }
        if (! preg_match('/\A[a-f0-9]{64}\z/', $signature)) {
            throw new InvalidPaymentWebhook('Razorpay webhook signature is invalid.');
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new InvalidPaymentWebhook('Razorpay webhook signature did not match.');
        }

        try {
            $body = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPaymentWebhook('Razorpay webhook payload is invalid.', 0, $exception);
        }
        if (! is_array($body) || ! is_string($body['event'] ?? null)) {
            throw new InvalidPaymentWebhook('Razorpay webhook payload is invalid.');
        }

        $type = $body['event'];
        $payment = $body['payload']['payment']['entity'] ?? null;
        $order = $body['payload']['order']['entity'] ?? null;
        $paymentReference = is_array($payment) && is_string($payment['id'] ?? null)
            ? $payment['id'] : null;
        $orderReference = is_array($payment) && is_string($payment['order_id'] ?? null)
            ? $payment['order_id']
            : (is_array($order) && is_string($order['id'] ?? null) ? $order['id'] : null);
        $digest = hash('sha256', $rawBody);
        $providerEventId = is_string($eventId) && $eventId !== ''
            ? substr($eventId, 0, 128)
            : $digest;

        DB::table('payment_webhook_events')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'provider' => 'razorpay',
            'provider_event_id' => $providerEventId,
            'event_type' => substr($type, 0, 64),
            'provider_order_reference' => $orderReference,
            'provider_payment_reference' => $paymentReference,
            'payload_digest' => $digest,
            'status' => 'received',
            'processed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receipt = DB::table('payment_webhook_events')
            ->where('provider', 'razorpay')
            ->where('provider_event_id', $providerEventId)
            ->first();
        if (! $receipt instanceof \stdClass) {
            throw new RuntimeException('Razorpay webhook receipt is unavailable.');
        }
        if (in_array($receipt->status, ['processed', 'ignored'], true)) {
            return;
        }

        try {
            if (
                in_array($type, ['payment.captured', 'order.paid'], true)
                && $orderReference !== null
            ) {
                $this->subscriptions->reconcileProviderCheckout(
                    $orderReference,
                    $paymentReference,
                );
                $status = 'processed';
            } elseif ($type === 'payment.failed' && $orderReference !== null) {
                // A Razorpay order can have another payment attempt. Keep the order pending;
                // only a captured payment plus a paid order is authoritative completion.
                $status = 'processed';
            } else {
                $status = 'ignored';
            }

            DB::table('payment_webhook_events')->where('id', $receipt->id)->update([
                'status' => $status,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('payment_webhook_events')->where('id', $receipt->id)->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);
            throw $exception;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Billing;

use App\Entitlements\Exceptions\EntitlementDenied;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class RazorpayPaymentProvider implements PaymentProvider
{
    public function publicKey(): string
    {
        return $this->keyId();
    }

    public function capture(CheckoutOrder $order): CheckoutCapture
    {
        $response = $this->request()->post($this->url('/orders'), [
            'amount' => $order->totalPaise,
            'currency' => 'INR',
            'receipt' => $order->checkoutId,
            'notes' => [
                'checkout_id' => $order->checkoutId,
                'plan_code' => $order->planCode,
            ],
        ]);

        $body = $this->successfulJson($response);
        $reference = $this->requiredString($body, 'id');

        if (
            ! str_starts_with($reference, 'order_')
            || ($body['status'] ?? null) !== 'created'
            || ($body['currency'] ?? null) !== 'INR'
            || ($body['amount'] ?? null) !== $order->totalPaise
            || ($body['receipt'] ?? null) !== $order->checkoutId
        ) {
            throw new RuntimeException('Razorpay returned an invalid order contract.');
        }

        return new CheckoutCapture(
            status: 'pending',
            provider: 'razorpay',
            providerReference: $reference,
            publicKey: $this->keyId(),
        );
    }

    public function confirm(
        CheckoutOrder $order,
        string $providerReference,
        string $providerPaymentReference,
        string $signature,
    ): CheckoutCapture {
        $expected = hash_hmac(
            'sha256',
            $providerReference.'|'.$providerPaymentReference,
            $this->keySecret(),
        );

        if (! hash_equals($expected, $signature)) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        return $this->reconcile($order, $providerReference, $providerPaymentReference);
    }

    public function reconcile(
        CheckoutOrder $order,
        string $providerReference,
        ?string $providerPaymentReference = null,
    ): CheckoutCapture {
        $orderBody = $this->successfulJson(
            $this->request()->get($this->url('/orders/'.rawurlencode($providerReference))),
        );

        if (
            ($orderBody['id'] ?? null) !== $providerReference
            || ($orderBody['currency'] ?? null) !== 'INR'
            || ($orderBody['amount'] ?? null) !== $order->totalPaise
            || ($orderBody['receipt'] ?? null) !== $order->checkoutId
        ) {
            throw new RuntimeException('Razorpay order reconciliation did not match checkout authority.');
        }

        if ($providerPaymentReference === null) {
            if (($orderBody['status'] ?? null) === 'paid') {
                $payments = $this->successfulJson(
                    $this->request()->get($this->url('/orders/'.rawurlencode($providerReference).'/payments')),
                );
                $items = $payments['items'] ?? null;
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (
                            is_array($item)
                            && ($item['status'] ?? null) === 'captured'
                            && ($item['order_id'] ?? null) === $providerReference
                            && ($item['amount'] ?? null) === $order->totalPaise
                            && is_string($item['id'] ?? null)
                        ) {
                            $providerPaymentReference = $item['id'];
                            break;
                        }
                    }
                }
            }
        }

        if ($providerPaymentReference === null) {
            return new CheckoutCapture(
                status: 'pending',
                provider: 'razorpay',
                providerReference: $providerReference,
                publicKey: $this->keyId(),
            );
        }

        $paymentBody = $this->successfulJson(
            $this->request()->get($this->url('/payments/'.rawurlencode($providerPaymentReference))),
        );

        if (
            ($paymentBody['id'] ?? null) !== $providerPaymentReference
            || ($paymentBody['order_id'] ?? null) !== $providerReference
            || ($paymentBody['currency'] ?? null) !== 'INR'
            || ($paymentBody['amount'] ?? null) !== $order->totalPaise
        ) {
            throw new RuntimeException('Razorpay payment reconciliation did not match checkout authority.');
        }

        $completed = ($paymentBody['status'] ?? null) === 'captured'
            && ($orderBody['status'] ?? null) === 'paid';

        return new CheckoutCapture(
            status: $completed ? 'completed' : 'pending',
            provider: 'razorpay',
            providerReference: $providerReference,
            providerPaymentReference: $providerPaymentReference,
            publicKey: $this->keyId(),
        );
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withBasicAuth($this->keyId(), $this->keySecret())
            ->connectTimeout(min(5, $this->timeout()))
            ->timeout($this->timeout());
    }

    /** @return array<string, mixed> */
    private function successfulJson(Response $response): array
    {
        if (! $response->successful()) {
            throw new EntitlementDenied(EntitlementDenied::CHECKOUT_UNAVAILABLE);
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('Razorpay returned a non-object response.');
        }

        return $body;
    }

    private function url(string $path): string
    {
        $base = config('billing.razorpay.api_base_url');
        if (! is_string($base) || ! str_starts_with($base, 'https://')) {
            throw new RuntimeException('Razorpay API base URL must use HTTPS.');
        }

        return rtrim($base, '/').$path;
    }

    private function keyId(): string
    {
        $value = config('billing.razorpay.key_id');
        $mode = config('billing.razorpay.mode');
        $prefix = $mode === 'live' ? 'rzp_live_' : 'rzp_test_';
        if (! is_string($value) || ! str_starts_with($value, $prefix)) {
            throw new RuntimeException('Razorpay key id does not match the configured mode.');
        }

        return $value;
    }

    private function keySecret(): string
    {
        $value = config('billing.razorpay.key_secret');
        if (! is_string($value) || strlen($value) < 16) {
            throw new RuntimeException('Razorpay key secret is unavailable.');
        }

        return $value;
    }

    private function timeout(): int
    {
        return max(2, min(30, (int) config('billing.razorpay.timeout_seconds', 10)));
    }

    /** @param array<string, mixed> $body */
    private function requiredString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Razorpay response is missing {$key}.");
        }

        return $value;
    }
}

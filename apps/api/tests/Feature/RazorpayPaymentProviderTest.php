<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\CheckoutOrder;
use App\Billing\RazorpayPaymentProvider;
use App\Entitlements\Exceptions\EntitlementDenied;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RazorpayPaymentProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'billing.razorpay.api_base_url' => 'https://api.razorpay.test/v1',
            'billing.razorpay.key_id' => 'rzp_test_public123456',
            'billing.razorpay.key_secret' => 'test-secret-1234567890',
            'billing.razorpay.mode' => 'test',
            'billing.razorpay.timeout_seconds' => 3,
        ]);
    }

    public function test_it_creates_a_server_side_order_without_exposing_the_secret(): void
    {
        Http::fake([
            'api.razorpay.test/v1/orders' => Http::response([
                'id' => 'order_test12345678',
                'status' => 'created',
                'amount' => 354,
                'currency' => 'INR',
                'receipt' => '01M2PAYMENTCHECKOUT000000001',
            ], 200),
        ]);

        $capture = app(RazorpayPaymentProvider::class)->capture($this->order());

        self::assertSame('pending', $capture->status);
        self::assertSame('razorpay', $capture->provider);
        self::assertSame('order_test12345678', $capture->providerReference);
        self::assertSame('rzp_test_public123456', $capture->publicKey);
        Http::assertSent(function ($request): bool {
            self::assertSame(354, $request['amount']);
            self::assertSame('INR', $request['currency']);
            self::assertSame('01M2PAYMENTCHECKOUT000000001', $request['receipt']);
            self::assertStringNotContainsString('test-secret', (string) $request->body());

            return true;
        });
    }

    public function test_it_confirms_only_a_signed_captured_payment_and_paid_order(): void
    {
        Http::fake([
            'api.razorpay.test/v1/orders/order_test12345678' => Http::response([
                'id' => 'order_test12345678',
                'status' => 'paid',
                'amount' => 354,
                'currency' => 'INR',
                'receipt' => '01M2PAYMENTCHECKOUT000000001',
            ], 200),
            'api.razorpay.test/v1/payments/pay_test12345678' => Http::response([
                'id' => 'pay_test12345678',
                'order_id' => 'order_test12345678',
                'status' => 'captured',
                'amount' => 354,
                'currency' => 'INR',
            ], 200),
        ]);
        $signature = hash_hmac(
            'sha256',
            'order_test12345678|pay_test12345678',
            'test-secret-1234567890',
        );

        $capture = app(RazorpayPaymentProvider::class)->confirm(
            $this->order(),
            'order_test12345678',
            'pay_test12345678',
            $signature,
        );

        self::assertSame('completed', $capture->status);
        self::assertSame('pay_test12345678', $capture->providerPaymentReference);
    }

    public function test_it_rejects_a_tampered_checkout_signature_before_provider_calls(): void
    {
        Http::fake();

        $this->expectException(EntitlementDenied::class);
        try {
            app(RazorpayPaymentProvider::class)->confirm(
                $this->order(),
                'order_test12345678',
                'pay_test12345678',
                str_repeat('0', 64),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    private function order(): CheckoutOrder
    {
        return new CheckoutOrder(
            checkoutId: '01M2PAYMENTCHECKOUT000000001',
            amountExGstPaise: 300,
            gstPaise: 54,
            totalPaise: 354,
            planCode: 'demo',
        );
    }
}

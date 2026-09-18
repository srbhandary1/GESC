<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Billing\InvalidPaymentWebhook;
use App\Billing\RazorpayWebhookProcessor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RazorpayWebhookController extends Controller
{
    public function __construct(private readonly RazorpayWebhookProcessor $processor) {}

    public function __invoke(Request $request): Response
    {
        try {
            $this->processor->process(
                $request->getContent(),
                (string) $request->header('X-Razorpay-Signature', ''),
                $request->header('X-Razorpay-Event-Id'),
            );
        } catch (InvalidPaymentWebhook) {
            abort(401);
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Review\DelayedReviewReturnDispatcher;
use App\Mail\DelayedReviewReturnMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class DelayedReviewReturnDispatcherTest extends TestCase
{
    public function test_fails_closed_without_privacy_approval(): void
    {
        Mail::fake();
        config([
            'review.return_email.enabled' => false,
            'review.return_email.approval' => '',
        ]);

        $result = app(DelayedReviewReturnDispatcher::class)->dispatchDue();

        $this->assertSame('disabled', $result['status']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame('privacy_gate', $result['skip_reason']);
        Mail::assertNothingSent();
    }

    public function test_mailable_contains_only_the_home_link(): void
    {
        $mailable = new DelayedReviewReturnMail('https://example.test/#home');
        $html = $mailable->content()->htmlString ?? '';

        $this->assertStringContainsString('https://example.test/#home', $html);
        $this->assertStringContainsString('Open Home', $html);
        $this->assertStringNotContainsString('transcript', strtolower($html));
        $this->assertStringNotContainsString('audio', strtolower($html));
        $this->assertDoesNotMatchRegularExpression('/level\s+\d+/i', $html);
    }

    public function test_enabled_without_plaintext_destination_does_not_send(): void
    {
        Mail::fake();
        config([
            'review.return_email.enabled' => true,
            'review.return_email.approval' => 'APP-PENDING-REVIEW',
        ]);

        $result = app(DelayedReviewReturnDispatcher::class)->dispatchDue();

        $this->assertSame('completed', $result['status']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['considered']);
        $this->assertSame('destination_unavailable', $result['skip_reason']);
        Mail::assertNothingSent();
    }
}

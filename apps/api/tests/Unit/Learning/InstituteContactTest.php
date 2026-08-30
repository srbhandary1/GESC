<?php

declare(strict_types=1);

namespace Tests\Unit\Learning;

use App\Learning\Tuition\InstituteContact;
use Tests\TestCase;

final class InstituteContactTest extends TestCase
{
    public function test_empty_config_hides_every_contact_link(): void
    {
        config()->set('tuition.institute_contact', [
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
        ]);

        $this->assertSame(
            [
                'phone' => null,
                'whatsapp' => null,
                'email' => null,
            ],
            $this->app->make(InstituteContact::class)->learnerPayload(),
        );
    }

    public function test_accepts_governed_phone_whatsapp_and_email(): void
    {
        config()->set('tuition.institute_contact', [
            'phone' => '+919876543210',
            'whatsapp' => '+91 98765 43210',
            'email' => 'direct@global-english.test',
        ]);

        $this->assertSame(
            [
                'phone' => '+919876543210',
                'whatsapp' => '919876543210',
                'email' => 'direct@global-english.test',
            ],
            $this->app->make(InstituteContact::class)->learnerPayload(),
        );
    }

    public function test_rejects_malformed_contact_values(): void
    {
        config()->set('tuition.institute_contact', [
            'phone' => 'call-us',
            'whatsapp' => 'abc',
            'email' => 'not-an-email',
        ]);

        $this->assertSame(
            [
                'phone' => null,
                'whatsapp' => null,
                'email' => null,
            ],
            $this->app->make(InstituteContact::class)->learnerPayload(),
        );
    }
}

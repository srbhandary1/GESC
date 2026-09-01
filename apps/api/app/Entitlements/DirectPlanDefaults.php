<?php

declare(strict_types=1);

namespace App\Entitlements;

final class DirectPlanDefaults
{
    public const string DEMO = 'demo';

    public const string MONTHLY_MODULES = 'monthly_modules';

    public const string MONTHLY_ROLEPLAY = 'monthly_modules_roleplay';

    public const string PLACEHOLDER_GSTIN = '29AAAAA0000A1Z5';

    public const int PLACEHOLDER_GST_RATE_BPS = 1800;

    public const int DEMO_DURATION_HOURS = 48;

    public const int DEMO_LIVE_AI_CAP_SECONDS = 300;

    public const int DEMO_MAX_LEVEL = 2;

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     amount_ex_gst_paise: int,
     *     billing_period: string,
     *     duration_value: int,
     *     allows_roleplay: bool,
     *     max_level_number: ?int,
     *     live_ai_cap_seconds: ?int,
     *     is_demo: bool
     * }>
     */
    public static function plans(): array
    {
        return [
            [
                'code' => self::DEMO,
                'name' => 'Two-day demo',
                'amount_ex_gst_paise' => 0,
                'billing_period' => 'hours',
                'duration_value' => self::DEMO_DURATION_HOURS,
                'allows_roleplay' => true,
                'max_level_number' => self::DEMO_MAX_LEVEL,
                'live_ai_cap_seconds' => self::DEMO_LIVE_AI_CAP_SECONDS,
                'is_demo' => true,
            ],
            [
                'code' => self::MONTHLY_MODULES,
                'name' => 'Modules',
                'amount_ex_gst_paise' => 35000,
                'billing_period' => 'calendar_month',
                'duration_value' => 1,
                'allows_roleplay' => false,
                'max_level_number' => null,
                'live_ai_cap_seconds' => null,
                'is_demo' => false,
            ],
            [
                'code' => self::MONTHLY_ROLEPLAY,
                'name' => 'Premium',
                'amount_ex_gst_paise' => 50000,
                'billing_period' => 'calendar_month',
                'duration_value' => 1,
                'allows_roleplay' => true,
                'max_level_number' => null,
                'live_ai_cap_seconds' => null,
                'is_demo' => false,
            ],
        ];
    }

    private function __construct() {}
}

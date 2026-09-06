<?php

declare(strict_types=1);

namespace App\Entitlements;

use stdClass;

final class DirectPlanDefaults
{
    public const string DEMO = 'demo';

    public const string MONTHLY_MODULES = 'monthly_modules';

    public const string MONTHLY_ROLEPLAY = 'monthly_modules_roleplay';

    public const string TALK_PACK_30 = 'talk_pack_30';

    public const string TALK_PACK_60 = 'talk_pack_60';

    public const string TALK_PACK_180 = 'talk_pack_180';

    public const string BILLING_TALK_PACK = 'talk_pack';

    public const string PLACEHOLDER_GSTIN = '29AAAAA0000A1Z5';

    public const int PLACEHOLDER_GST_RATE_BPS = 1800;

    public const int DEMO_DURATION_HOURS = 48;

    public const int DEMO_LIVE_AI_CAP_SECONDS = 300;

    public const int MODULES_LIVE_AI_CAP_SECONDS = 2400;

    public const int PREMIUM_LIVE_AI_CAP_SECONDS = 5400;

    public const int TALK_PACK_30_SECONDS = 1800;

    public const int TALK_PACK_60_SECONDS = 3600;

    public const int TALK_PACK_180_SECONDS = 10800;

    public const int TALK_PACK_MAX_DAYS = 30;

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
                'live_ai_cap_seconds' => self::MODULES_LIVE_AI_CAP_SECONDS,
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
                'live_ai_cap_seconds' => self::PREMIUM_LIVE_AI_CAP_SECONDS,
                'is_demo' => false,
            ],
            [
                'code' => self::TALK_PACK_30,
                'name' => 'Talk 30',
                'amount_ex_gst_paise' => 19900,
                'billing_period' => self::BILLING_TALK_PACK,
                'duration_value' => self::TALK_PACK_MAX_DAYS,
                'allows_roleplay' => false,
                'max_level_number' => null,
                'live_ai_cap_seconds' => self::TALK_PACK_30_SECONDS,
                'is_demo' => false,
            ],
            [
                'code' => self::TALK_PACK_60,
                'name' => 'Talk 60',
                'amount_ex_gst_paise' => 34900,
                'billing_period' => self::BILLING_TALK_PACK,
                'duration_value' => self::TALK_PACK_MAX_DAYS,
                'allows_roleplay' => false,
                'max_level_number' => null,
                'live_ai_cap_seconds' => self::TALK_PACK_60_SECONDS,
                'is_demo' => false,
            ],
            [
                'code' => self::TALK_PACK_180,
                'name' => 'Talk 180',
                'amount_ex_gst_paise' => 89900,
                'billing_period' => self::BILLING_TALK_PACK,
                'duration_value' => self::TALK_PACK_MAX_DAYS,
                'allows_roleplay' => false,
                'max_level_number' => null,
                'live_ai_cap_seconds' => self::TALK_PACK_180_SECONDS,
                'is_demo' => false,
            ],
        ];
    }

    public static function isTalkPack(stdClass $plan): bool
    {
        $period = $plan->billing_period ?? null;

        return is_string($period) && $period === self::BILLING_TALK_PACK;
    }

    public static function membershipLiveAiCap(string $code): ?int
    {
        return match ($code) {
            self::DEMO => self::DEMO_LIVE_AI_CAP_SECONDS,
            self::MONTHLY_MODULES => self::MODULES_LIVE_AI_CAP_SECONDS,
            self::MONTHLY_ROLEPLAY => self::PREMIUM_LIVE_AI_CAP_SECONDS,
            default => null,
        };
    }

    private function __construct() {}
}

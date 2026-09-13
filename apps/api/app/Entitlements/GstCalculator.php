<?php

declare(strict_types=1);

namespace App\Entitlements;

final class GstCalculator
{
    /**
     * @return array{amount_ex_gst_paise: int, gst_paise: int, total_paise: int, gst_rate_bps: int}
     */
    public static function payable(int $amountExGstPaise, int $gstRateBps, bool $gstRegistered): array
    {
        $rate = $gstRegistered ? $gstRateBps : 0;
        $gst = (int) intdiv(($amountExGstPaise * $rate) + 5000, 10000);

        return [
            'amount_ex_gst_paise' => $amountExGstPaise,
            'gst_paise' => $gst,
            'total_paise' => $amountExGstPaise + $gst,
            'gst_rate_bps' => $rate,
        ];
    }

    private function __construct() {}
}

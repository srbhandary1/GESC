<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\LessonPractice\DirectPathwayCommissioningAuditService;

final class DirectPathwayCommissioningAuditTest extends TenancyIntegrationTestCase
{
    public function test_audit_reports_commissioning_pending_when_direct_program_has_no_publications(): void
    {
        $result = app(DirectPathwayCommissioningAuditService::class)->audit();

        self::assertSame('LPL-05', $result['checkpoint']);
        self::assertSame('WAVE-7.1', $result['approval_reference']);
        self::assertSame(17, $result['levels_configured']);
        self::assertFalse($result['all_complete']);
        self::assertSame(0, $result['levels_complete']);
        self::assertSame(
            17,
            count(array_filter(
                $result['levels'],
                static fn (array $row): bool => ($row['availability'] ?? '') === 'commissioning_pending',
            )),
        );
        self::assertSame(
            'blueprint_not_published',
            $result['levels'][0]['unavailable_reason'] ?? null,
        );
        self::assertFalse($result['waves']['wave_1_level_1']['all_complete']);
        self::assertFalse($result['waves']['wave_2_levels_2_3']['all_complete']);
        self::assertFalse($result['waves']['wave_3_levels_4_17']['all_complete']);
    }
}

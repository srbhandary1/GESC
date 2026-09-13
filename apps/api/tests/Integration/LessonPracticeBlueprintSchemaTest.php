<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\Schema;

final class LessonPracticeBlueprintSchemaTest extends TenancyIntegrationTestCase
{
    public function test_lpl02_tables_exist_with_tenant_scoped_uniqueness(): void
    {
        self::assertTrue(Schema::hasTable('lesson_practice_blueprints'));
        self::assertTrue(Schema::hasTable('lesson_practice_reviews'));
        self::assertTrue(Schema::hasTable('lesson_practice_media'));
        self::assertTrue(Schema::hasTable('lesson_practice_attachment_events'));
        self::assertContains('content_hash', Schema::getColumnListing('lesson_practice_blueprints'));
        self::assertContains('idempotency_key', Schema::getColumnListing('lesson_practice_attachment_events'));
    }
}

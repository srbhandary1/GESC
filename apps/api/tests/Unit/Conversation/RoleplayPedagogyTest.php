<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\RoleplayCatalog;
use App\Conversation\Support\RoleplayPedagogy;
use Tests\TestCase;

final class RoleplayPedagogyTest extends TestCase
{
    public function test_every_catalog_entry_has_authored_observe_rehearse_perform_scripts(): void
    {
        $scripts = RoleplayPedagogy::scripts();
        $this->assertCount(12, $scripts);

        foreach (RoleplayCatalog::entries() as $entry) {
            $pedagogy = $entry['pedagogy'] ?? null;
            $this->assertIsArray($pedagogy, $entry['id']);
            $this->assertNotEmpty($pedagogy['observe_model']);
            $this->assertNotEmpty($pedagogy['rehearse_chunks']);
            $this->assertNotSame('', $pedagogy['perform_prompt']);
            $this->assertSame($scripts[$entry['id']], $pedagogy);
        }
    }
}

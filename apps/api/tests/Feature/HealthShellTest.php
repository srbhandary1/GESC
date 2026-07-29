<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthShellTest extends TestCase
{
    public function test_framework_health_shell_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FederatedSignInTests;
use Tests\TestCase;

final class FederatedSignInTest extends TestCase
{
    use FederatedSignInTests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareFederatedSignIn();
    }
}

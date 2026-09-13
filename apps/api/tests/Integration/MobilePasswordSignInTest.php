<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MobilePasswordSignInTests;
use Tests\TestCase;

final class MobilePasswordSignInTest extends TestCase
{
    use MobilePasswordSignInTests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareMobilePasswordSignIn();
    }
}

<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

interface ProviderResult
{
    public function usage(): ProviderUsage;
}

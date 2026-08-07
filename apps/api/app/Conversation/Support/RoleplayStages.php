<?php

declare(strict_types=1);

namespace App\Conversation\Support;

final class RoleplayStages
{
    public const OBSERVE = 'observe';

    public const REHEARSE = 'rehearse';

    public const PERFORM = 'perform';

    public const REFLECT = 'reflect';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::OBSERVE,
            self::REHEARSE,
            self::PERFORM,
            self::REFLECT,
        ];
    }

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::all(), true);
    }

    public static function nextStage(string $stage): ?string
    {
        $stages = self::all();
        $index = array_search($stage, $stages, true);

        if ($index === false) {
            return null;
        }

        return $stages[$index + 1] ?? null;
    }
}

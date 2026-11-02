<?php

namespace App\Filament\Concerns;

use App\Models\Project;
use App\Support\Money;
use Filament\Facades\Filament;

trait UsesCurrentProject
{
    public static function project(): Project
    {
        /** @var Project */
        return Filament::getTenant();
    }

    public static function money(?int $minor): string
    {
        return Money::format($minor, static::project()->currency);
    }
}

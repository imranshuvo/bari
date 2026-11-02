<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Belt and braces: Filament already limits resources to the current project,
 * but every query here also filters on project_id explicitly.
 */
trait ScopedToCurrentProject
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->where($query->qualifyColumn('project_id'), static::project()->getKey());
    }
}

<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    public function getTitle(): string
    {
        return __('Activity');
    }

    public function getSubheading(): ?string
    {
        return __('Who added, changed or deleted what.');
    }
}

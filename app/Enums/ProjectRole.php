<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ProjectRole: string implements HasColor, HasDescription, HasLabel
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Editor => __('Editor'),
            self::Viewer => __('Viewer'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Owner => __('Can do everything, including budget, team and settings.'),
            self::Editor => __('Can add and edit costs, agreements and suppliers.'),
            self::Viewer => __('Can only look.'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'primary',
            self::Editor => 'info',
            self::Viewer => 'gray',
        };
    }

    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}

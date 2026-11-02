<?php

namespace App\Support;

/** Typical house-building budget categories offered when a project is created. */
class CategoryTemplate
{
    public const NAMES = [
        'Land & legal',
        'Design & permits',
        'Site preparation',
        'Foundation',
        'Structure & walls',
        'Roof',
        'Windows & doors',
        'Plumbing',
        'Electrical',
        'Heating, cooling & ventilation',
        'Interior finishes',
        'Flooring & tiles',
        'Kitchen',
        'Bathrooms',
        'Painting',
        'Outdoor & landscaping',
        'Utility connections & fees',
        'Other',
    ];

    /** @return array<string, string> English key => name in the current language */
    public static function options(): array
    {
        return collect(self::NAMES)->mapWithKeys(fn (string $name) => [$name => __($name)])->all();
    }
}

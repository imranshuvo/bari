<?php

namespace App\Filament\Forms;

use App\Models\Category;
use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Category picker that, for owners, also offers to add whatever name is typed.
 * A new category is only created when the form is saved (with a budget of 0,
 * to be set later on the Budget page), so cancelling leaves nothing behind.
 */
class CategorySelect
{
    /** Prefix for a typed name that is not a category yet. */
    public const NEW = 'new:';

    public static function make(string $name = 'category_id'): Select
    {
        return Select::make($name)
            ->label(__('Category'))
            ->native(false)
            ->searchable()
            ->preload()
            ->options(fn (): array => self::names())
            ->getSearchResultsUsing(fn (?string $search): array => self::searchResults($search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::label($value))
            ->searchPrompt(fn (): string => self::canAdd() ? __('Type a name to search or add a new category') : __('Type to search'))
            ->noSearchResultsMessage(fn (): ?string => self::canAdd() ? null : __('No category with that name. Only owners can add categories.'))
            ->dehydrateStateUsing(fn (mixed $state): mixed => self::resolve($state));
    }

    /**
     * @return array<int|string, string>
     */
    public static function searchResults(?string $search): array
    {
        $search = Str::squish((string) $search);
        $names = self::names();

        if ($search === '') {
            return $names;
        }

        $needle = Str::lower($search);
        $matches = array_filter($names, fn (string $name): bool => str_contains(Str::lower($name), $needle));
        $exists = collect($names)->contains(fn (string $name): bool => Str::lower($name) === $needle);

        if (! $exists && self::canAdd() && mb_strlen($search) <= 120) {
            $matches = [self::NEW.$search => __('Add “:name”', ['name' => $search])] + $matches;
        }

        return $matches;
    }

    /** The category id for a picked option, creating the category when it is new. */
    public static function resolve(mixed $state): mixed
    {
        $name = self::newName($state);

        if ($name === null) {
            return $state;
        }

        $project = self::project();

        return $project->categories()->whereRaw('lower(name) = ?', [Str::lower($name)])->value('id')
            ?? $project->categories()->create(['name' => $name])->getKey();
    }

    /** Whether the state is a category id (and not a name still to be added). */
    public static function isExisting(mixed $state): bool
    {
        return is_int($state) || (is_string($state) && ctype_digit($state));
    }

    private static function label(mixed $value): ?string
    {
        if (($name = self::newName($value)) !== null) {
            return self::canAdd() ? $name : null;
        }

        return self::isExisting($value) ? (self::names()[(int) $value] ?? null) : null;
    }

    private static function newName(mixed $state): ?string
    {
        if (! is_string($state) || ! str_starts_with($state, self::NEW)) {
            return null;
        }

        $name = Str::squish(Str::after($state, self::NEW));

        return $name === '' ? null : Str::limit($name, 120, '');
    }

    private static function canAdd(): bool
    {
        return Gate::allows('create', Category::class);
    }

    /**
     * @return array<int, string>
     */
    private static function names(): array
    {
        return self::project()->categories()->orderBy('sort')->pluck('name', 'id')->all();
    }

    private static function project(): Project
    {
        return Filament::getTenant();
    }
}

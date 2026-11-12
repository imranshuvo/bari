<?php

namespace App\Filament\Forms;

use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Str;

/**
 * "Paid to" / "With whom?": a tap-to-open list of everyone already in the project,
 * which also offers to add whatever name is typed. The state is the supplier's name,
 * so saving code resolves (or creates) the supplier exactly as before.
 *
 * Replaces a text box with a <datalist>, which phones don't show as a dropdown.
 */
class SupplierSelect
{
    public static function make(string $name = 'supplier_name'): Select
    {
        return Select::make($name)
            ->native(false)
            ->searchable()
            ->preload()
            ->options(fn (): array => self::names())
            ->getSearchResultsUsing(fn (?string $search): array => self::searchResults($search))
            ->getOptionLabelUsing(fn (?string $value): ?string => $value)
            ->searchPrompt(__('Type a name to search or add someone new'))
            ->noSearchResultsMessage(__('Type a name to add someone new'))
            ->rule('max:120');
    }

    /**
     * @return array<string, string>
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

        if (! $exists && mb_strlen($search) <= 120) {
            $matches = [$search => __('Add “:name”', ['name' => $search])] + $matches;
        }

        return $matches;
    }

    /**
     * @return array<string, string>
     */
    private static function names(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Project) {
            return [];
        }

        return $tenant->suppliers()->orderBy('name')->pluck('name', 'name')->all();
    }
}

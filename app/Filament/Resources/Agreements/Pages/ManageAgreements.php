<?php

namespace App\Filament\Resources\Agreements\Pages;

use App\Filament\Resources\Agreements\AgreementResource;
use App\Models\Agreement;
use App\Models\Supplier;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Arr;

class ManageAgreements extends ManageRecords
{
    protected static string $resource = AgreementResource::class;

    public function getTitle(): string
    {
        return __('Agreed');
    }

    public function getSubheading(): ?string
    {
        return __('Prices you have agreed with contractors and shops. Click one to see its bills.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Add agreed price'))
                ->modalHeading(__('Add agreed price'))
                ->slideOver()
                ->modalWidth('lg')
                ->createAnother(false)
                ->using(fn (array $data) => static::save($data)),
        ];
    }

    public static function save(array $data, ?Agreement $agreement = null): Agreement
    {
        $project = AgreementResource::project();
        $supplier = Supplier::resolve($project, $data['supplier_name'] ?? null);

        $agreement ??= new Agreement;
        $agreement->fill(Arr::except($data, ['supplier_name']));
        $agreement->project_id = $project->id;
        $agreement->supplier_id = $supplier->id;
        $agreement->save();

        // Bills linked to this agreement follow its supplier and category.
        $agreement->costs()->each(fn ($cost) => $cost->save());

        return $agreement;
    }
}

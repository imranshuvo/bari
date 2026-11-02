<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSuppliers extends ManageRecords
{
    protected static string $resource = SupplierResource::class;

    public function getTitle(): string
    {
        return __('Suppliers');
    }

    public function getSubheading(): ?string
    {
        return __('Everyone you have paid. Click one to see their bills.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Add supplier'))
                ->modalHeading(__('Add supplier'))
                ->modalWidth('lg')
                ->createAnother(false)
                ->mutateDataUsing(fn (array $data) => [...$data, 'project_id' => SupplierResource::project()->id]),
        ];
    }
}

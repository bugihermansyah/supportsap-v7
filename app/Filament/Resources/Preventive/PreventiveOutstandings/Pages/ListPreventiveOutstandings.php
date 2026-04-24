<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Pages;

use App\Filament\Resources\Preventive\PreventiveOutstandings\PreventiveOutstandingResource;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Widgets\OutstandingStat;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPreventiveOutstandings extends ListRecords
{
    protected static string $resource = PreventiveOutstandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OutstandingStat::class,
        ];
    }
    
    public function getTabs(): array
    {
        return [
            null => Tab::make('All'),
            'open' => Tab::make()->query(fn ($query) => $query->where('status', 0)),
            'closed' => Tab::make()->query(fn ($query) => $query->where('status', 1)),
        ];
    }
}

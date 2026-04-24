<?php

namespace App\Filament\Resources\Outstandings\Pages;

use App\Filament\Resources\Outstandings\OutstandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutstandings extends ListRecords
{
    protected static string $resource = OutstandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

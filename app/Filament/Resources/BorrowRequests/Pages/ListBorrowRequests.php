<?php

namespace App\Filament\Resources\BorrowRequests\Pages;

use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBorrowRequests extends ListRecords
{
    protected static string $resource = BorrowRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

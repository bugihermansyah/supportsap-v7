<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportReportings extends ListRecords
{
    protected static string $resource = SupportReportingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

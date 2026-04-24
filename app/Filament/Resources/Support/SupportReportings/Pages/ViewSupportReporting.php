<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportReporting extends ViewRecord
{
    protected static string $resource = SupportReportingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

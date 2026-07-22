<?php

namespace App\Filament\Resources\ReportingEmails\Pages;

use App\Filament\Resources\ReportingEmails\ReportingEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReportingEmails extends ManageRecords
{
    protected static string $resource = ReportingEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}

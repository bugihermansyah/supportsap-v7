<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Pages;

use App\Filament\Resources\Preventive\PreventiveOutstandings\PreventiveOutstandingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPreventiveOutstanding extends ViewRecord
{
    protected static string $resource = PreventiveOutstandingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

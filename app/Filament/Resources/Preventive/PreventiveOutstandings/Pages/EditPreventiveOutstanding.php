<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Pages;

use App\Filament\Resources\Preventive\PreventiveOutstandings\PreventiveOutstandingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPreventiveOutstanding extends EditRecord
{
    protected static string $resource = PreventiveOutstandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

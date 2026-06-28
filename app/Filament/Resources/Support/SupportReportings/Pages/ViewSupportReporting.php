<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportReporting extends ViewRecord
{
    protected static string $resource = SupportReportingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('evaluate')
                ->label('Update Evaluation')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->fillForm(SupportReportingResource::getEvaluationFillFormCallback())
                ->form(SupportReportingResource::getEvaluationFormSchema())
                ->action(SupportReportingResource::getEvaluationActionCallback()),
            Actions\EditAction::make(),
        ];
    }
}

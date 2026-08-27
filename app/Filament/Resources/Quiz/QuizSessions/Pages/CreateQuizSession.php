<?php

namespace App\Filament\Resources\Quiz\QuizSessions\Pages;

use App\Filament\Resources\Quiz\QuizSessions\QuizSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuizSession extends CreateRecord
{
    protected static string $resource = QuizSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

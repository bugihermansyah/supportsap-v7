<?php

namespace App\Filament\Resources\Quiz\QuizQuestions\Pages;

use App\Filament\Resources\Quiz\QuizQuestions\QuizQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuizQuestion extends CreateRecord
{
    protected static string $resource = QuizQuestionResource::class;

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

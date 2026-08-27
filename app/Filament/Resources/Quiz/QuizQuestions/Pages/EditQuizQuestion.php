<?php

namespace App\Filament\Resources\Quiz\QuizQuestions\Pages;

use App\Filament\Resources\Quiz\QuizQuestions\QuizQuestionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuizQuestion extends EditRecord
{
    protected static string $resource = QuizQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

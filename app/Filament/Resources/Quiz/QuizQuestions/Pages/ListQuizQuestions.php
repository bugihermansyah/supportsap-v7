<?php

namespace App\Filament\Resources\Quiz\QuizQuestions\Pages;

use App\Filament\Resources\Quiz\QuizQuestions\QuizQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuizQuestions extends ListRecords
{
    protected static string $resource = QuizQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

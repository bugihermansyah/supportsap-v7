<?php

namespace App\Filament\Resources\Quiz\QuizSessions\Pages;

use App\Filament\Resources\Quiz\QuizSessions\QuizSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuizSessions extends ListRecords
{
    protected static string $resource = QuizSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

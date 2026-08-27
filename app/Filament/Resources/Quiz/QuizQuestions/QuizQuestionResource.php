<?php

namespace App\Filament\Resources\Quiz\QuizQuestions;

use App\Filament\Resources\Quiz\QuizQuestions\Pages\CreateQuizQuestion;
use App\Filament\Resources\Quiz\QuizQuestions\Pages\EditQuizQuestion;
use App\Filament\Resources\Quiz\QuizQuestions\Pages\ListQuizQuestions;
use App\Filament\Resources\Quiz\QuizQuestions\Schemas\QuizQuestionForm;
use App\Filament\Resources\Quiz\QuizQuestions\Tables\QuizQuestionsTable;
use App\Models\QuizQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuizQuestionResource extends Resource
{
    protected static ?string $model = QuizQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Quiz';

    protected static ?string $navigationLabel = 'Bank Soal';

    protected static ?string $modelLabel = 'Soal';

    protected static ?string $pluralModelLabel = 'Bank Soal';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return QuizQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuizQuestionsTable::configure($table);
    }

    /** Soal dikelola oleh helpdesk. */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['helpdesk', 'admin', 'super_admin', 'manager']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizQuestions::route('/'),
            'create' => CreateQuizQuestion::route('/create'),
            'edit' => EditQuizQuestion::route('/{record}/edit'),
        ];
    }
}

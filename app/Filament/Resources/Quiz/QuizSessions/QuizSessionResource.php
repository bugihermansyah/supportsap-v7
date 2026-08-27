<?php

namespace App\Filament\Resources\Quiz\QuizSessions;

use App\Filament\Resources\Quiz\QuizSessions\Pages\CreateQuizSession;
use App\Filament\Resources\Quiz\QuizSessions\Pages\EditQuizSession;
use App\Filament\Resources\Quiz\QuizSessions\Pages\ListQuizSessions;
use App\Filament\Resources\Quiz\QuizSessions\Schemas\QuizSessionForm;
use App\Filament\Resources\Quiz\QuizSessions\Tables\QuizSessionsTable;
use App\Models\QuizSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuizSessionResource extends Resource
{
    protected static ?string $model = QuizSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Quiz';

    protected static ?string $navigationLabel = 'Sesi Quiz';

    protected static ?string $modelLabel = 'Sesi Quiz';

    protected static ?string $pluralModelLabel = 'Sesi Quiz';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return QuizSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuizSessionsTable::configure($table);
    }

    /** Sesi quiz dikelola oleh helpdesk. */
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['helpdesk', 'admin', 'super_admin', 'manager']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuizSessions::route('/'),
            'create' => CreateQuizSession::route('/create'),
            'edit' => EditQuizSession::route('/{record}/edit'),
        ];
    }
}

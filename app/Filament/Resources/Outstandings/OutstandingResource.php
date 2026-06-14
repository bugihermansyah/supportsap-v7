<?php

namespace App\Filament\Resources\Outstandings;

use App\Filament\Resources\Outstandings\Pages\CreateOutstanding;
use App\Filament\Resources\Outstandings\Pages\EditOutstanding;
use App\Filament\Resources\Outstandings\Pages\ListOutstandings;
use App\Filament\Resources\Outstandings\Pages\ManageReportingOutstanding;
use App\Filament\Resources\Outstandings\Pages\ViewOutstanding;
use App\Filament\Resources\Outstandings\RelationManagers\ReportingsRelationManager;
use App\Filament\Resources\Outstandings\Schemas\OutstandingForm;
use App\Filament\Resources\Outstandings\Schemas\OutstandingInfolist;
use App\Filament\Resources\Outstandings\Tables\OutstandingsTable;
use App\Models\Outstanding;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OutstandingResource extends Resource
{
    protected static ?string $model = Outstanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;
    
    protected static string |\UnitEnum| null $navigationGroup = 'Work';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $modelLabel = 'Outstanding';

    protected static ?string $navigationLabel = 'Outstanding';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return OutstandingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OutstandingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutstandingsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && $user->hasAnyRole(['head_support', 'support'])) {
            $query->whereHas('location', fn ($q) => $q->where('team_id', $user->team_id));
        }

        return $query;
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditOutstanding::class,
            ManageReportingOutstanding::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutstandings::route('/'),
            'create' => CreateOutstanding::route('/create'),
            'edit' => EditOutstanding::route('/{record}/edit'),
            'reportings' => ManageReportingOutstanding::route('/{record}/reportings'),
        ];
    }
}

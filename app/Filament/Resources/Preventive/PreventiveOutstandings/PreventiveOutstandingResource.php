<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings;

use App\Filament\Resources\Preventive\PreventiveOutstandings\Pages\CreatePreventiveOutstanding;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Pages\EditPreventiveOutstanding;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Pages\ListPreventiveOutstandings;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Pages\ViewPreventiveOutstanding;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Schemas\PreventiveOutstandingForm;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Schemas\PreventiveOutstandingInfolist;
use App\Filament\Resources\Preventive\PreventiveOutstandings\Tables\PreventiveOutstandingsTable;
use App\Models\Outstanding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PreventiveOutstandingResource extends Resource
{
    protected static ?string $model = Outstanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'number';
    
    protected static ?string $modelLabel = 'Outstanding (Preventive)';

    protected static ?string $navigationLabel = 'Outstanding (Preventive)';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('head_preventive', 'preventive');
    }

    public static function form(Schema $schema): Schema
    {
        return PreventiveOutstandingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PreventiveOutstandingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreventiveOutstandingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreventiveOutstandings::route('/'),
            'create' => CreatePreventiveOutstanding::route('/create'),
            'view' => ViewPreventiveOutstanding::route('/{record}'),
            'edit' => EditPreventiveOutstanding::route('/{record}/edit'),
        ];
    }
}

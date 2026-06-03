<?php

namespace App\Filament\Resources\Support\SupportReportings;

use App\Filament\Resources\Support\SupportReportings\Pages\CreateSupportReporting;
use App\Filament\Resources\Support\SupportReportings\Pages\EditSupportReporting;
use App\Filament\Resources\Support\SupportReportings\Pages\ListSupportReportings;
use App\Filament\Resources\Support\SupportReportings\Pages\SendEmailReporting;
use App\Filament\Resources\Support\SupportReportings\Pages\ViewSupportReporting;
use App\Filament\Resources\Support\SupportReportings\Schemas\SupportReportingForm;
use App\Filament\Resources\Support\SupportReportings\Schemas\SupportReportingInfolist;
use App\Filament\Resources\Support\SupportReportings\Tables\SupportReportingsTable;
use App\Models\Reporting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportReportingResource extends Resource
{
    protected static ?string $model = Reporting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Work';

    // protected static ?string $recordTitleAttribute = 'location_title';

    protected static ?string $modelLabel = 'Daily Reporting';

    protected static ?string $navigationLabel = 'Daily Reporting';

    public static function form(Schema $schema): Schema
    {
        return SupportReportingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportReportingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportReportingsTable::configure($table);
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
            'index' => ListSupportReportings::route('/'),
            'create' => CreateSupportReporting::route('/create'),
            'view' => ViewSupportReporting::route('/{record}'),
            'edit' => EditSupportReporting::route('/{record}/edit'),
            'send-email' => SendEmailReporting::route('/{record}/send-email'),
        ];
    }
}

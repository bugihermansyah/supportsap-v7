<?php

namespace App\Filament\Resources\BorrowRequests;

use App\Filament\Resources\BorrowRequests\Pages\CreateBorrowRequest;
use App\Filament\Resources\BorrowRequests\Pages\EditBorrowRequest;
use App\Filament\Resources\BorrowRequests\Pages\ListBorrowRequests;
use App\Filament\Resources\BorrowRequests\Schemas\BorrowRequestForm;
use App\Filament\Resources\BorrowRequests\Tables\BorrowRequestsTable;
use App\Models\BorrowRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BorrowRequestResource extends Resource
{
    protected static ?string $model = BorrowRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected static ?string $recordTitleAttribute = 'rp_no';

    public static function form(Schema $schema): Schema
    {
        return BorrowRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BorrowRequestsTable::configure($table);
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
            'index' => ListBorrowRequests::route('/'),
            'create' => CreateBorrowRequest::route('/create'),
            'edit' => EditBorrowRequest::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('address')
                    ->columnSpanFull(),
                Toggle::make('use_stock')
                    ->label('Catat stok')
                    ->helperText('Aktif = gudang internal support; stok dicatat dan request lewat menu Stock Request. Nonaktif = gudang logistik eksternal (Borrow Request).')
                    ->default(true),
            ]);
    }
}

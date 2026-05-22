<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Enums\TypeContract;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Produk')
                    ->options(Product::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('gate')
                    ->label('Unit'),
                ToggleButtons::make('type_contract')
                    ->label('Tipe Kontrak')
                    ->inline()
                    ->options(TypeContract::class)
                    ->default(TypeContract::Sewa)
                    ->required(),
                DatePicker::make('bap')
                    ->label('BAP')
                    ->native(false),
                RichEditor::make('description')
                    ->label('Keterangan')
                    ->toolbarButtons([
                        'bold',
                        'bulletList',
                        'italic',
                        'orderedList',
                        'underline',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk'),
                TextColumn::make('gate')
                    ->label('Unit'),
                TextColumn::make('type_contract')
                    ->label('Kontrak'),
                TextColumn::make('bap')
                    ->label('BAP')
                    ->date(),
                TextColumn::make('description')
                    ->label('Deskripsi'),
                ToggleColumn::make('is_default')
                    ->label('Default'),
                ToggleColumn::make('status')
                    ->label('Status'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

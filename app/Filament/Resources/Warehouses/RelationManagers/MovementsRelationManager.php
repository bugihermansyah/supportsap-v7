<?php

namespace App\Filament\Resources\Warehouses\RelationManagers;

use App\Enums\StockMovementType;
use App\Models\Warehouse;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Buku besar stok — hanya baca. Koreksi dilakukan lewat Adjustment,
 * bukan dengan mengubah/menghapus baris di sini.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Riwayat Stok';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Warehouse && (bool) $ownerRecord->use_stock;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('unit.name')
                    ->label('Unit')
                    ->searchable(),
                TextColumn::make('movement_type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->alignCenter(),
                TextColumn::make('stock_before')
                    ->label('Sebelum')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_after')
                    ->label('Sesudah')
                    ->alignCenter(),
                TextColumn::make('stockRequest.id')
                    ->label('Request')
                    ->prefix('SR-')
                    ->placeholder('-'),
                TextColumn::make('creator.name')
                    ->label('Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('Tipe')
                    ->options(StockMovementType::class)
                    ->multiple(),
            ]);
    }
}

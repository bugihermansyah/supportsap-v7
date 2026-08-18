<?php

namespace App\Filament\Resources\Warehouses\RelationManagers;

use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseUnit;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Stok per unit di satu gudang. Hanya relevan untuk gudang use_stock = 1.
 *
 * Angka stok tidak boleh diedit langsung — semua perubahan lewat aksi
 * Stock In / Adjustment agar tercatat di stock_movements.
 */
class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Stok Unit';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Warehouse && (bool) $ownerRecord->use_stock;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_id')
            ->columns([
                TextColumn::make('unit.name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qty_total')
                    ->label('Total')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('qty_available')
                    ->label('Tersedia')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('qty_borrowed')
                    ->label('Dipinjam')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('stock_in')
                    ->label('Stock In')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->schema([
                        Select::make('unit_id')
                            ->label('Unit')
                            ->options(fn (): array => Unit::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('qty')
                            ->label('Jumlah Masuk')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Keterangan')
                            ->rows(2),
                    ])
                    ->action(function (array $data, StockService $stock) {
                        $stock->stockIn(
                            (int) $this->getOwnerRecord()->getKey(),
                            $data['unit_id'],
                            (int) $data['qty'],
                            $data['notes'] ?? null,
                        );

                        Notification::make()->title('Stok masuk tercatat')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('adjust')
                    ->label('Adjustment')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->schema([
                        TextInput::make('delta')
                            ->label('Koreksi (+/-)')
                            ->helperText('Isi negatif untuk mengurangi, mis. -2')
                            ->numeric()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Alasan')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (WarehouseUnit $record, array $data, StockService $stock) {
                        try {
                            $stock->adjust(
                                (int) $record->warehouse_id,
                                $record->unit_id,
                                (int) $data['delta'],
                                $data['notes'],
                            );
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title('Adjustment gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Stok dikoreksi')->success()->send();
                    }),
            ]);
    }
}

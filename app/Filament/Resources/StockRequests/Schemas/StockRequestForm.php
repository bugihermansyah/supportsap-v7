<?php

namespace App\Filament\Resources\StockRequests\Schemas;

use App\Enums\BorrowRequestType;
use App\Enums\LocationStatus;
use App\Models\StockRequest;
use App\Models\Warehouse;
use App\Models\WarehouseUnit;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Request')
                            ->columnSpan(1)
                            ->schema([
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->options(fn (): array => Warehouse::usesStock()->pluck('name', 'id')->all())
                                    ->default(fn () => Warehouse::usesStock()->value('id'))
                                    ->required()
                                    ->live()
                                    ->disabledOn('edit')
                                    ->helperText('Hanya gudang internal yang stoknya dicatat.'),
                                Select::make('request_type')
                                    ->label('Jenis Request')
                                    ->options(BorrowRequestType::class)
                                    ->default(BorrowRequestType::Replacement->value)
                                    ->required(),
                                Select::make('location_id')
                                    ->label('Lokasi')
                                    ->relationship('location', 'name', function ($query) {
                                        $query->with('company')
                                            ->where('status', '!=', LocationStatus::InActive);

                                        $user = auth()->user();

                                        if ($user && $user->hasAnyRole(['head_support', 'support'])) {
                                            $query->where(fn ($q) => $q
                                                ->where('team_id', $user->team_id)
                                                ->orWhere('area_status', 'out'));
                                        }
                                    })
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Textarea::make('note')
                                    ->label('Catatan')
                                    ->rows(2),
                            ]),

                        Section::make('Info')
                            ->columnSpan(1)
                            ->hidden(fn (?StockRequest $record) => $record === null)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                                TextEntry::make('requester.name')
                                    ->label('Requester'),
                                TextEntry::make('approver.name')
                                    ->label('Disetujui oleh')
                                    ->placeholder('Belum disetujui'),
                                TextEntry::make('approved_at')
                                    ->label('Waktu approve')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('-'),
                                TextEntry::make('releaser.name')
                                    ->label('Dikeluarkan oleh')
                                    ->placeholder('Belum keluar gudang'),
                                TextEntry::make('released_at')
                                    ->label('Waktu keluar')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('List Unit')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('units')
                            ->relationship()
                            ->hiddenLabel()
                            ->defaultItems(1)
                            ->required()
                            ->compact()
                            // Setelah stok dibooking, isi unit tidak boleh berubah —
                            // perubahan harus lewat aksi Return / Cancel agar stok ikut terkoreksi.
                            ->disabled(fn (?StockRequest $record) => $record?->status?->holdsStock() ?? false)
                            ->table([
                                TableColumn::make('Unit'),
                                TableColumn::make('Qty')->width('110px'),
                                TableColumn::make('Dikembalikan')->width('130px'),
                                TableColumn::make('Catatan'),
                            ])
                            ->schema([
                                Select::make('unit_id')
                                    ->label('Unit')
                                    ->options(fn (Get $get): array => static::availableUnits($get('../../warehouse_id')))
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live(),
                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->maxValue(fn (Get $get): ?int => static::availableQty(
                                        $get('../../warehouse_id'),
                                        $get('unit_id'),
                                    ))
                                    ->helperText(fn (Get $get): ?string => static::stockHint(
                                        $get('../../warehouse_id'),
                                        $get('unit_id'),
                                    )),
                                TextInput::make('returned_qty')
                                    ->label('Dikembalikan')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('note')
                                    ->label('Catatan')
                                    ->maxLength(255),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    /** @return array<string, string> */
    protected static function availableUnits(mixed $warehouseId): array
    {
        if (blank($warehouseId)) {
            return [];
        }

        return WarehouseUnit::query()
            ->with('unit')
            ->where('warehouse_id', $warehouseId)
            ->where('qty_available', '>', 0)
            ->get()
            ->mapWithKeys(fn (WarehouseUnit $stock) => [
                $stock->unit_id => ($stock->unit->name ?? $stock->unit_id)." — tersedia {$stock->qty_available}",
            ])
            ->all();
    }

    protected static function availableQty(mixed $warehouseId, mixed $unitId): ?int
    {
        if (blank($warehouseId) || blank($unitId)) {
            return null;
        }

        return WarehouseUnit::where('warehouse_id', $warehouseId)
            ->where('unit_id', $unitId)
            ->value('qty_available');
    }

    protected static function stockHint(mixed $warehouseId, mixed $unitId): ?string
    {
        $available = static::availableQty($warehouseId, $unitId);

        return $available === null ? null : "Stok tersedia: {$available}";
    }
}

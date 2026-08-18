<?php

namespace App\Filament\Resources\StockRequests;

use App\Filament\Resources\StockRequests\Pages\CreateStockRequest;
use App\Filament\Resources\StockRequests\Pages\EditStockRequest;
use App\Filament\Resources\StockRequests\Pages\ListStockRequests;
use App\Filament\Resources\StockRequests\Schemas\StockRequestForm;
use App\Filament\Resources\StockRequests\Tables\StockRequestsTable;
use App\Models\StockRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Request unit dari gudang internal divisi support (warehouses.use_stock = 1).
 *
 * Terpisah dari BorrowRequestResource yang melayani gudang logistik eksternal
 * (use_stock = 0, alur RP/SO/KRM lewat email). Bedanya di sini: stok dicatat.
 *
 * Pembagian peran mengikuti BorrowRequest:
 *   - admin & helpdesk  -> pengelola gudang (approve, keluarkan, terima retur)
 *   - head_support & support -> requester, hanya melihat requestnya sendiri/timnya
 */
class StockRequestResource extends Resource
{
    protected static ?string $model = StockRequest::class;

    /** Role yang mengelola stok gudang internal. */
    public const MANAGER_ROLES = ['super_admin', 'admin', 'helpdesk'];

    protected static string|\UnitEnum|null $navigationGroup = 'Work';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'Request Unit Support';

    protected static ?string $navigationLabel = 'Request Unit Support';

    protected static ?int $navigationSort = 3;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof StockRequest) {
            return null;
        }

        $type = $record->request_type->getLabel();

        return "{$type} - ".($record->location->full_name ?? $record->location->name ?? 'Unknown Location');
    }

    /** Pengelola gudang — boleh approve, release, terima retur, hapus. */
    public static function isManager(): bool
    {
        return auth()->user()?->hasAnyRole(self::MANAGER_ROLES) ?? false;
    }

    /**
     * Scope requester, mengikuti pola BorrowRequestsTable::modifyQueryUsing():
     * support hanya requestnya sendiri, head_support seluruh lokasi timnya
     * (plus request timnya untuk lokasi luar area).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user || static::isManager()) {
            return $query;
        }

        if ($user->hasRole('head_support')) {
            return $query->where(fn (Builder $q) => $q
                ->whereHas('location', fn (Builder $loc) => $loc->where('team_id', $user->team_id))
                ->orWhere(fn (Builder $q2) => $q2
                    ->whereHas('location', fn (Builder $loc) => $loc->where('area_status', 'out'))
                    ->whereHas('requester', fn (Builder $req) => $req->where('team_id', $user->team_id))));
        }

        if ($user->hasRole('support')) {
            return $query->where('requester_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return StockRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockRequests::route('/'),
            'create' => CreateStockRequest::route('/create'),
            'edit' => EditStockRequest::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Pages\Reports;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use App\Models\Unit;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecapBorrowUnit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Reports';

    protected string $view = 'filament.pages.rekap-peminjaman';

    protected static ?string $navigationLabel = 'Recap Borrow Unit';

    protected ?string $heading = 'Recap Borrow Unit';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Unit::query()
                    ->join('borrow_request_units', 'units.id', '=', 'borrow_request_units.unit_id')
                    ->join('borrow_requests', 'borrow_requests.id', '=', 'borrow_request_units.borrow_request_id')
                    ->where('borrow_requests.request_type', '!=', 'pull_request')
                    ->where('borrow_requests.warehouse_id', 1)
                    ->whereNotIn('borrow_requests.status', ['rejected', 'cancelled'])
                    ->select('units.id', 'units.name as unit_name', DB::raw('SUM(borrow_request_units.qty) as total_qty'))
                    ->groupBy('units.id', 'units.name')
            )
            ->columns([
                TextColumn::make('unit_name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_qty')
                    ->label('Qty')
                    ->sortable(),
            ])
            ->defaultSort('unit_name', 'asc');
    }
}

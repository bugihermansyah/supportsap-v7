<?php

namespace App\Filament\Pages\Reports;

use App\Enums\BorrowRequestStatus;
use App\Models\Location;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Unit;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class RecapBorrowUnit extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Reports';

    protected string $view = 'filament.pages.rekap-peminjaman';

    protected static ?string $navigationLabel = 'Recap Borrow Unit';

    protected ?string $heading = 'Recap Borrow Unit';

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
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('unit_name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_qty')
                    ->label('Qty')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('requester')
                    ->label('Requester')
                    ->options(fn (): array => User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $requesterId): Builder => $query->where('borrow_requests.requester_id', $requesterId)
                        );
                    }),
                SelectFilter::make('location')
                    ->label('Location')
                    ->options(fn (): array => Location::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $locationId): Builder => $query->where('borrow_requests.location_id', $locationId)
                        );
                    }),
                Filter::make('approved_from')
                    ->schema([
                        DatePicker::make('approved_from')
                            ->label('From Date Request')
                            ->default(now()->startOfMonth()->toDateString()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['approved_from'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereExists(function (QueryBuilder $q) use ($date) {
                                $q->select(DB::raw(1))
                                    ->from('borrow_request_logs')
                                    ->whereColumn('borrow_request_logs.borrow_request_id', 'borrow_requests.id')
                                    ->where('action', BorrowRequestStatus::Approved->value)
                                    ->whereDate('created_at', '>=', $date);
                            })
                        );
                    }),
                Filter::make('approved_until')
                    ->schema([
                        DatePicker::make('approved_until')
                            ->label('Until Date Request')
                            ->default(now()->endOfMonth()->toDateString()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['approved_until'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereExists(function (QueryBuilder $q) use ($date) {
                                $q->select(DB::raw(1))
                                    ->from('borrow_request_logs')
                                    ->whereColumn('borrow_request_logs.borrow_request_id', 'borrow_requests.id')
                                    ->where('action', BorrowRequestStatus::Approved->value)
                                    ->whereDate('created_at', '<=', $date);
                            })
                        );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->defaultSort('unit_name', 'asc')
            ->headerActions([
                ExportAction::make()->exports([
                    ExcelExport::make()
                        ->askForWriterType()
                        ->withFilename(date('Y-m-d H:i:s') . ' - recap-borrow-unit')
                        ->fromTable()
                ])
                    
            ]);
    }
}

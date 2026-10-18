<?php

namespace App\Filament\Pages\Reports;

use App\Enums\OutstandingTypeProblem;
use App\Models\OutstandingUnit;
use App\Models\Team;
use App\Models\Unit;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportCountOutstandingUnit extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected string $view = 'filament.pages.reports.report-count-outstanding-unit';

    protected static ?string $title = 'Report Outstanding by Unit';

    protected static ?string $navigationLabel = 'Outstanding by Unit';

    protected static string|\UnitEnum|null $navigationGroup = 'Support Reports';

    protected static ?int $navigationSort = 12;

    public function table(Table $table): Table
    {
        return $table
            ->query(Unit::query())
            ->modifyQueryUsing(function (Builder $query, HasTable $livewire) {
                // Retrieve filter state from individual select filters
                $year = $livewire->tableFilters['year']['value'] ?? now()->year;
                $month = $livewire->tableFilters['month']['value'] ?? now()->month;
                $reporter = $livewire->tableFilters['reporter']['value'] ?? null;
                $team = $livewire->tableFilters['team']['value'] ?? null;

                $user = auth()->user();

                $unitTotals = OutstandingUnit::query()
                    ->join('outstandings', 'outstandings.id', '=', 'outstanding_units.outstanding_id')
                    ->join('locations', 'locations.id', '=', 'outstandings.location_id')
                    ->select('outstanding_units.unit_id')
                    ->selectRaw(
                        'SUM(CASE WHEN outstandings.is_type_problem = ? THEN COALESCE(outstanding_units.qty, 0) ELSE 0 END) as hw_qty,
                        SUM(CASE WHEN outstandings.is_type_problem = ? THEN COALESCE(outstanding_units.qty, 0) ELSE 0 END) as sw_qty,
                        SUM(CASE WHEN outstandings.is_type_problem = ? THEN COALESCE(outstanding_units.qty, 0) ELSE 0 END) as civil_qty,
                        SUM(CASE WHEN outstandings.is_type_problem = ? THEN COALESCE(outstanding_units.qty, 0) ELSE 0 END) as hw_non_qty,
                        SUM(COALESCE(outstanding_units.qty, 0)) as total_qty',
                        [
                            OutstandingTypeProblem::HW->value,
                            OutstandingTypeProblem::SW->value,
                            OutstandingTypeProblem::CIVIL->value,
                            OutstandingTypeProblem::NON->value,
                        ],
                    )
                    ->whereNull('outstanding_units.deleted_at');

                if ($year) {
                    $unitTotals->whereYear('outstandings.date_in', $year);
                }

                if ($month) {
                    $unitTotals->whereMonth('outstandings.date_in', $month);
                }

                if ($reporter) {
                    $unitTotals->where('outstandings.reporter', $reporter);
                }

                if ($user->hasRole('helpdesk') && $team) {
                    $unitTotals->where('locations.team_id', $team);
                } elseif ($user->hasRole('head_support')) {
                    $unitTotals->where('locations.team_id', $user->team_id);
                }

                $query
                    ->leftJoinSub($unitTotals->groupBy('outstanding_units.unit_id'), 'unit_problem_totals', function ($join): void {
                        $join->on('units.id', '=', 'unit_problem_totals.unit_id');
                    })
                    ->select('units.id', 'units.name')
                    ->selectRaw(
                        'COALESCE(unit_problem_totals.hw_qty, 0) as hw_qty,
                        COALESCE(unit_problem_totals.sw_qty, 0) as sw_qty,
                        COALESCE(unit_problem_totals.civil_qty, 0) as civil_qty,
                        COALESCE(unit_problem_totals.hw_non_qty, 0) as hw_non_qty,
                        COALESCE(unit_problem_totals.total_qty, 0) as total_qty',
                    );
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hw_qty')->label('H/W')->sortable(),
                TextColumn::make('sw_qty')->label('S/W')->sortable(),
                TextColumn::make('civil_qty')->label('Sipil')->sortable(),
                TextColumn::make('hw_non_qty')->label('H/W-Non')->sortable(),
                TextColumn::make('total_qty')
                    ->label('Total')
                    ->sortable()
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(function () {
                        $years = OutstandingUnit::query()
                            ->join('outstandings', 'outstandings.id', '=', 'outstanding_units.outstanding_id')
                            ->selectRaw('YEAR(outstandings.date_in) as year')
                            ->whereNotNull('outstandings.date_in')
                            ->distinct()
                            ->pluck('year')
                            ->toArray();
                        $currentYear = now()->year;
                        if (! in_array($currentYear, $years)) {
                            $years[] = $currentYear;
                        }
                        rsort($years);

                        return array_combine($years, $years);
                    })
                    ->default(now()->year)
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                        '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                    ])
                    ->default(now()->month)
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('reporter')
                    ->label('Reporter')
                    ->options([
                        'client' => 'Client',
                        'preventif' => 'Preventif',
                        'support' => 'Internal/Support',
                    ])
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('team')
                    ->label('Team')
                    ->options(fn () => Team::pluck('name', 'id'))
                    ->visible(fn () => auth()->user()?->hasRole('helpdesk'))
                    ->query(fn (Builder $query) => $query),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc('total_qty')
                ->orderBy('units.name'))
            ->defaultKeySort(false);
    }
}

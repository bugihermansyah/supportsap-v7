<?php

namespace App\Filament\Pages\Reports;

use App\Models\Unit;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ReportCountOutstandingUnit extends Page implements HasTable
{
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
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query, \Filament\Tables\Contracts\HasTable $livewire) {
                // Retrieve filter state from individual select filters
                $year = $livewire->tableFilters['year']['value'] ?? now()->year;
                $month = $livewire->tableFilters['month']['value'] ?? now()->month;
                $reporter = $livewire->tableFilters['reporter']['value'] ?? null;
                $team = $livewire->tableFilters['team']['value'] ?? null;
                
                $user = auth()->user();
                
                $query->withCount(['outstandings' => function ($q) use ($year, $month, $reporter, $team, $user) {
                    if ($year) {
                        $q->whereYear('date_in', $year);
                    }
                    if ($month) {
                        $q->whereMonth('date_in', $month);
                    }
                    if ($reporter) {
                        $q->where('reporter', $reporter);
                    }
                    
                    if ($user->hasRole('helpdesk')) {
                        if ($team) {
                            $q->whereHas('location', function ($lq) use ($team) {
                                $lq->where('team_id', $team);
                            });
                        }
                    } elseif ($user->hasRole('head_support')) {
                        $q->whereHas('location', function ($lq) use ($user) {
                            $lq->where('team_id', $user->team_id);
                        });
                    }
                }]);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Unit Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('outstandings_count')
                    ->label('Total Outstanding')
                    ->sortable()
                    ->badge(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(function () {
                        $years = \App\Models\Outstanding::selectRaw('YEAR(date_in) as year')->whereNotNull('date_in')->distinct()->pluck('year')->toArray();
                        $currentYear = now()->year;
                        if (!in_array($currentYear, $years)) $years[] = $currentYear;
                        rsort($years);
                        return array_combine($years, $years);
                    })
                    ->default(now()->year)
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query),
                \Filament\Tables\Filters\SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                        '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                    ])
                    ->default(now()->month)
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query),
                \Filament\Tables\Filters\SelectFilter::make('reporter')
                    ->label('Reporter')
                    ->options([
                        'client' => 'Client',
                        'preventif' => 'Preventif',
                        'support' => 'Internal/Support',
                    ])
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query),
                \Filament\Tables\Filters\SelectFilter::make('team')
                    ->label('Team')
                    ->options(fn () => \App\Models\Team::pluck('name', 'id'))
                    ->visible(fn () => auth()->user()?->hasRole('helpdesk'))
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->defaultSort('outstandings_count', 'desc');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'head_support', 'helpdesk']) ?? false;
    }
}

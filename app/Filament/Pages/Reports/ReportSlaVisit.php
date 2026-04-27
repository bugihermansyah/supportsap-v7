<?php

namespace App\Filament\Pages\Reports;

use App\Models\Product;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ReportSlaVisit extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected string $view = 'filament.pages.reports.report-count-outstanding'; // Reusing the same simple blade view

    protected static ?string $title = 'Report SLA Visit/Remote';

    protected static ?string $navigationLabel = 'SLA Visit';

    protected static string|\UnitEnum|null $navigationGroup = 'Report';

    protected static ?int $navigationSort = 14;

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query())
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query, \Filament\Tables\Contracts\HasTable $livewire) {
                $year = $livewire->tableFilters['year']['value'] ?? now()->year;
                $month = $livewire->tableFilters['month']['value'] ?? now()->month;
                $reporter = $livewire->tableFilters['reporter']['value'] ?? null;
                $team = $livewire->tableFilters['team']['value'] ?? null;
                $lpm = $livewire->tableFilters['lpm']['value'] ?? null;
                
                $user = auth()->user();
                
                $baseFilter = function ($q) use ($year, $month, $reporter, $team, $user, $lpm) {
                    if ($year) {
                        $q->whereYear('date_in', $year);
                    }
                    if ($month) {
                        $q->whereMonth('date_in', $month);
                    }
                    if ($reporter) {
                        $q->where('reporter', $reporter);
                    }
                    if ($lpm !== null && $lpm !== '') {
                        $q->where('lpm', $lpm == '1' || $lpm === true);
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
                    
                    $q->whereNotNull('date_visit');
                };
                
                $query->withCount([
                    'outstandings as sla1_count' => function ($q) use ($baseFilter) {
                        $baseFilter($q);
                        $q->whereRaw('DATEDIFF(date_visit, date_in) BETWEEN 0 AND 1');
                    },
                    'outstandings as sla2_count' => function ($q) use ($baseFilter) {
                        $baseFilter($q);
                        $q->whereRaw('DATEDIFF(date_visit, date_in) BETWEEN 2 AND 3');
                    },
                    'outstandings as sla3_count' => function ($q) use ($baseFilter) {
                        $baseFilter($q);
                        $q->whereRaw('DATEDIFF(date_visit, date_in) > 3');
                    },
                ]);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sla1_count')
                    ->label('SLA 1 (0 - 1)')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('sla2_count')
                    ->label('SLA 2 (2 - 3)')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('sla3_count')
                    ->label('SLA 3 (> 3)')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('group')
                    ->label('Group')
                    ->options(function () {
                        return Product::query()->whereNotNull('group')->distinct()->pluck('group', 'group')->toArray();
                    }),
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
                \Filament\Tables\Filters\TernaryFilter::make('lpm')
                    ->label('Laporan /1 masuk')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->defaultSort('name', 'asc');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'head_support', 'helpdesk']) ?? false;
    }
}

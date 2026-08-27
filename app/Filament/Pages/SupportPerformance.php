<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SupportPerformance\AverageKpiChart;
use App\Filament\Widgets\SupportPerformance\EngineerGridWidget;
use App\Filament\Widgets\SupportPerformance\PerformanceRankingWidget;
use App\Filament\Widgets\SupportPerformance\TeamSummaryWidget;
use App\Models\Team;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportPerformance extends BaseDashboard
{
    use HasFiltersForm;
    use HasPageShield;

    protected static string|\UnitEnum|null $navigationGroup = 'Main';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Support Performance';

    protected static ?string $title = 'Support Performance Center';

    protected static ?int $navigationSort = 2;

    protected static string $routePath = '/support-performance';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->components([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now()->endOfMonth()),
                        Select::make('team_id')
                            ->label('Team')
                            ->options(Team::pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'super_admin', 'helpdesk'])),
                        // Filter Support / Customer / Location dihapus: tidak dibutuhkan.
                        // Kode pembacaannya di widget ikut dibuang, jadi halaman ini
                        // hanya disaring lewat tanggal dan team.
                        // Select::make('status')
                        //     ->label('Status')
                        //     ->options([
                        //         'online' => 'Online',
                        //         'offline' => 'Offline',
                        //         'working' => 'Working',
                        //         'outside_city' => 'Outside City',
                        //     ]),
                        // Select::make('kpi_level')
                        //     ->label('KPI Level')
                        //     ->options([
                        //         'excellent' => 'Excellent (>=110)',
                        //         'good' => 'Good (100-109)',
                        //         'normal' => 'Normal (90-99)',
                        //         'need_improvement' => 'Need Improvement (<90)',
                        //     ]),
                    ])
                    ->columns(4)
                    ->columnSpan('full'),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            TeamSummaryWidget::class,
            EngineerGridWidget::class,
            PerformanceRankingWidget::class,
            AverageKpiChart::class,
        ];
    }
}

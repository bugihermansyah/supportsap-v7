<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Models\Location;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class SupportPerformance extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|\UnitEnum|null $navigationGroup = 'Main';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Support Performance';
    protected static ?string $title = 'Support Performance Center';
    protected static ?int $navigationSort = 2;
    protected static string $routePath = '/support-performance';

    public function filtersForm(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
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
                            ->visible(fn () => auth()->user()->hasAnyRole(['head_support', 'manager', 'super_admin', 'helpdesk'])),
                        // Select::make('user_id')
                        //     ->label('Engineer')
                        //     ->options(User::pluck('name', 'id'))
                        //     ->searchable(),
                        // Select::make('customer_id')
                        //     ->label('Customer')
                        //     ->options(Customer::pluck('name', 'id'))
                        //     ->searchable(),
                        // Select::make('location_id')
                        //     ->label('Area / Location')
                        //     ->options(Location::pluck('name', 'id'))
                        //     ->searchable(),
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
            \App\Filament\Widgets\SupportPerformance\TeamSummaryWidget::class,
            \App\Filament\Widgets\SupportPerformance\EngineerGridWidget::class,
            \App\Filament\Widgets\SupportPerformance\PerformanceRankingWidget::class,
            \App\Filament\Widgets\SupportPerformance\AverageKpiChart::class,
        ];
    }
}

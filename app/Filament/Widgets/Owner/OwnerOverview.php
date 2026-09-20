<?php

namespace App\Filament\Widgets\Owner;

use App\Enums\OutstandingStatus;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OwnerOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    public function getColumns(): int|array
    {
        return [
            'md' => 3,
            'xl' => 6,
        ];
    }

    protected function getStats(): array
    {
        $totalLocations = DB::table('locations')
            ->where('status', '!=', 'inactive')
            ->count();

        $latestStatuses = DB::table('reportings as r1')
            ->select('r1.outstanding_id', 'r1.status')
            ->whereIn('r1.id', function ($query) {
                $query->from('reportings')
                    ->selectRaw('MAX(id)')
                    ->groupBy('outstanding_id');
            });

        $counts = DB::table(DB::raw("({$latestStatuses->toSql()}) as latest"))
            ->mergeBindings($latestStatuses)
            ->join('outstandings', 'outstandings.id', '=', 'latest.outstanding_id')
            ->where('outstandings.status', OutstandingStatus::Open)
            ->where('outstandings.is_implement', 0)
            ->where('outstandings.date_in', '<=', now()->subDays(3))
            ->where('outstandings.reporter', '!=', 'preventif')
            ->selectRaw('SUM(CASE WHEN latest.status = 0 THEN 1 ELSE 0 END) AS pending_sap,
                SUM(CASE WHEN latest.status = 2 THEN 1 ELSE 0 END) AS pending_client,
                SUM(CASE WHEN latest.status = 3 THEN 1 ELSE 0 END) AS temporary,
                SUM(CASE WHEN latest.status = 4 THEN 1 ELSE 0 END) AS monitoring')
            ->first();

        $totalImplementasi = DB::table('outstandings')
            ->where('is_implement', 1)
            ->where('status', '!=', 1)
            ->count();

        return [
            Stat::make('Location', $totalLocations)
                ->icon('heroicon-o-map-pin'),
            Stat::make('Pending SAP', $counts->pending_sap ?? 0)
                ->icon('heroicon-o-clock'),
            Stat::make('Pending Client', $counts->pending_client ?? 0)
                ->icon('heroicon-o-user-group'),
            Stat::make('Temporary', $counts->temporary ?? 0)
                ->icon('heroicon-o-wrench'),
            Stat::make('Monitoring', $counts->monitoring ?? 0)
                ->icon('heroicon-o-eye'),
            Stat::make('Implementasi', $totalImplementasi)
                ->icon('heroicon-o-arrow-trending-up'),
        ];
    }
}

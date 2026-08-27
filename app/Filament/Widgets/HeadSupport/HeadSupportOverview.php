<?php

namespace App\Filament\Widgets\HeadSupport;

use App\Enums\OutstandingStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class HeadSupportOverview extends StatsOverviewWidget
{
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
        $user = auth()->user();

        // Initialize the query for locations
        $locationQuery = DB::table('locations')
            ->where('status', '!=', 'inactive');

        // Apply team filter if the user has a team and is not an admin
        if ($user->team_id && ! $user->hasRole('admin')) {
            $locationQuery->where('team_id', $user->team_id);
        }

        $totalLocations = $locationQuery->count();

        // Get the count of locations with 'area_status' as 'out'
        // $areaLocations = $locationQuery->clone()->where('area_status', 'out')->count();

        // Initialize the query for outstandings
        $outstandingQuery = DB::table('outstandings')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('outstandings.status', OutstandingStatus::Open);

        // Apply team filter for outstandings based on user's team and location
        if ($user->team_id && ! $user->hasRole('admin')) {
            $outstandingQuery->where('locations.team_id', $user->team_id);
        }

        // $openOutstanding = $outstandingQuery->count();

        // Laporan terakhir per outstanding lewat MAX(id) (ULID sudah urut waktu, dan
        // created_at kembar tidak lagi menghasilkan baris dobel). Di sini subquery
        // dijalankan penuh sebagai derived table, jadi bentuk IN + GROUP BY jauh lebih
        // murah (26 ms) daripada korelasi per baris (78 ms).
        $latestStatuses = DB::table('reportings as r1')
            ->select('r1.outstanding_id', 'r1.status')
            ->whereIn('r1.id', function ($query) {
                $query->from('reportings')
                    ->selectRaw('MAX(id)')
                    ->groupBy('outstanding_id');
            });

        // Gabungkan ke outstandings dan locations agar bisa filter berdasarkan team_id
        $countsQuery = DB::table(DB::raw("({$latestStatuses->toSql()}) as latest"))
            ->mergeBindings($latestStatuses)
            ->join('outstandings', 'outstandings.id', '=', 'latest.outstanding_id')
            ->join('locations', 'locations.id', '=', 'outstandings.location_id')
            ->where('outstandings.status', OutstandingStatus::Open)
            ->where('outstandings.is_implement', 0)
            ->where('outstandings.date_in', '<=', now()->subDays(3))
            ->where('outstandings.reporter', '!=', 'preventif')
            ->selectRaw('
                SUM(CASE WHEN latest.status = 0 THEN 1 ELSE 0 END) AS pending_sap,
                SUM(CASE WHEN latest.status = 2 THEN 1 ELSE 0 END) AS pending_client,
                SUM(CASE WHEN latest.status = 3 THEN 1 ELSE 0 END) AS temporary,
                SUM(CASE WHEN latest.status = 4 THEN 1 ELSE 0 END) AS monitoring
            ');

        // Tambahkan kondisi filter berdasarkan team user
        if ($user->team_id && ! $user->hasRole('admin')) {
            $countsQuery->where('locations.team_id', $user->team_id);
        }

        $counts = $countsQuery->first();
        // Tambahan implementasi
        $implementasiQuery = DB::table('outstandings')
            ->join('locations', 'locations.id', '=', 'outstandings.location_id')
            ->where('outstandings.is_implement', 1)
            ->where('outstandings.status', '!=', 1); // status finish = 1

        if ($user->team_id && ! $user->hasRole('admin')) {
            $implementasiQuery->where('locations.team_id', $user->team_id);
        }

        $totalImplementasi = $implementasiQuery->count();

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

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['head_support', 'manager', 'helpdesk']);
    }
}

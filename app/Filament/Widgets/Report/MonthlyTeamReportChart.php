<?php

namespace App\Filament\Widgets\Report;

use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Reporting;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class MonthlyTeamReportChart extends ApexChartWidget
{
    use HasFiltersSchema;

    protected static bool $isDiscovered = false;

    protected bool $hasDeferredFilters = true;

    protected static ?string $loadingIndicator = 'Loading...';

    protected static ?string $heading = 'Monthly Team Report Summary';

    protected int | string | array $columnSpan = 'full';

    // protected static ?string $maxHeight = '500px';

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('year')
                ->label('Year')
                ->options($this->getYearOptions())
                ->default((string) now()->year),
            Select::make('team')
                ->label('Team')
                ->placeholder('All Teams')
                ->options(\App\Models\Team::pluck('name', 'id')),
        ]);
    }

    public function applyDeferredFilters(): void
    {
        $this->filters = $this->deferredFilters;
        $this->updateOptions();
    }

    public function resetDeferredFilters(): void
    {
        $this->getFiltersSchema()->fill();
        $this->filters = $this->deferredFilters;
        $this->updateOptions();
    }

    protected function getYearOptions(): array
    {
        $years = \App\Models\Outstanding::selectRaw('YEAR(date_in) as year')
            ->whereNotNull('date_in')
            ->distinct()
            ->pluck('year')
            ->toArray();

        $currentYear = now()->year;
        if (!in_array($currentYear, $years)) {
            $years[] = $currentYear;
        }

        rsort($years);

        return array_combine($years, $years);
    }

    protected function getOptions(): array
    {
        $year = (int) ($this->filters['year'] ?? now()->year);
        $teamId = $this->filters['team'] ?? null;

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 500,
            ],
            'series' => [
                [
                    'name' => 'Total Lokasi',
                    'data' => $this->getCumulativeLocationsPerMonth($year, $teamId),
                ],
                [
                    'name' => 'Total Outstanding',
                    'data' => $this->getTotalOutstandingPerMonth($year, $teamId),
                ],
                [
                    'name' => 'Total Lokasi Masalah',
                    'data' => $this->getUniqueLocationsPerMonth($year, $teamId),
                ],
                [
                    'name' => 'Total Visit',
                    'data' => $this->getTotalVisitData($year, $teamId),
                ],
                [
                    'name' => 'Total LPM 1',
                    'data' => $this->getLaporanAwalMasukData($year, $teamId),
                ],
                [
                    'name' => 'Total SLA Visit',
                    'data' => $this->getTotalSlaVisitData($year, $teamId),
                ],
                [
                    'name' => 'Total Remote',
                    'data' => $this->getTotalRemoteData($year, $teamId),
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'dataLabels' => [
                        'orientation' => 'vertical',
                        'position' => 'center',
                    ],
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'style' => [
                    'fontFamily' => 'inherit',
                ],
            ],
            'xaxis' => [
                'categories' => $months,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'tooltip' => [
                'shared' => true,
                'intersect' => false
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#f97316', '#22d3ee'],
        ];
    }

    protected function getTotalOutstandingPerMonth($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Outstanding::query()
            ->selectRaw('MONTH(date_in) as month, COUNT(*) as count')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('outstandings.reporter', '=', 'client')
            ->whereYear('date_in', $year);

        if ($teamId) {
            $query->where('locations.team_id', $teamId);
        }

        $results = $query->groupByRaw('MONTH(date_in)')->get();

        foreach ($results as $result) {
            $data[$result->month - 1] = (int) $result->count;
        }

        return $data;
    }

    protected function getCumulativeLocationsPerMonth($year, $teamId): array
    {
        // Inisialisasi array data untuk 12 bulan
        $data = array_fill(0, 12, 0);

        // Hitung total lokasi sebelum tahun yang dipilih
        $previousTotal = Location::query()
            ->whereYear('created_at', '<', $year)
            ->whereNot('locations.status', 'dismantle');

        if ($teamId) {
            $previousTotal->where('team_id', $teamId);
        }

        $previousTotal = $previousTotal->count(); // Total lokasi sebelum tahun yang dipilih

        $currentYear = now()->year;
        $currentMonth = now()->month;

        // Perulangan untuk setiap bulan dalam tahun yang dipilih
        for ($month = 1; $month <= 12; $month++) {
            // Jika tahun yang dipilih adalah tahun ini, jangan hitung bulan yang belum lewat (set ke 0)
            if ($year == $currentYear && $month > $currentMonth) {
                $data[$month - 1] = 0;
                continue;
            }

            $endDate = \Carbon\Carbon::create($year, $month)->endOfMonth()->format('Y-m-d 23:59:59');

            $query = Location::query()
                ->whereBetween('created_at', ["$year-01-01 00:00:00", $endDate]) // Hitung lokasi dalam tahun ini sampai akhir bulan tertentu
                ->whereNot('locations.status', 'dismantle');

            if ($teamId) {
                $query->where('team_id', $teamId);
            }

            // Hitung total lokasi dalam tahun yang dipilih sampai bulan tertentu
            $totalLocations = $query->count();

            // Akumulasi dengan total lokasi dari tahun sebelumnya
            $data[$month - 1] = $previousTotal + $totalLocations;
        }

        return $data;
    }

    protected function getUniqueLocationsPerMonth($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Outstanding::query()
            ->selectRaw('MONTH(date_in) as month, COUNT(DISTINCT outstandings.location_id) as count')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('outstandings.status', '0') // Open / masalah
            ->where('outstandings.reporter', '=', 'client')
            ->whereYear('date_in', $year);

        if ($teamId) {
            $query->where('locations.team_id', $teamId);
        }

        $results = $query->groupByRaw('MONTH(date_in)')->get();

        foreach ($results as $result) {
            $data[$result->month - 1] = (int) $result->count;
        }

        return $data;
    }

    protected function getTotalVisitData($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Reporting::selectRaw('MONTH(reportings.date_visit) as month, COUNT(*) as count')
            ->join('outstandings', 'reportings.outstanding_id', '=', 'outstandings.id')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('reportings.work', 'visit')
            ->where('outstandings.reporter', '=', 'client')
            ->whereYear('reportings.date_visit', $year);

        if ($teamId) {
            $query->where('locations.team_id', $teamId);
        }

        $results = $query->groupByRaw('MONTH(reportings.date_visit)')->get();

        foreach ($results as $result) {
            $data[$result->month - 1] = (int) $result->count;
        }

        return $data;
    }

    protected function getLaporanAwalMasukData($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Outstanding::selectRaw('MONTH(date_in) as month, COUNT(*) as count')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('outstandings.lpm', 1)
            ->where('outstandings.reporter', '=', 'client')
            ->whereYear('date_in', $year);

        if ($teamId) {
            $query->where('locations.team_id', $teamId);
        }

        $results = $query->groupByRaw('MONTH(date_in)')->get();

        foreach ($results as $result) {
            $data[$result->month - 1] = (int) $result->count;
        }

        return $data;
    }

    protected function getTotalSlaVisitData($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Outstanding::selectRaw('MONTH(date_in) as month, COUNT(*) as count')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->whereNotNull('outstandings.date_visit')
            ->whereRaw('DATEDIFF(outstandings.date_visit, outstandings.date_in) <= 3')
            ->where('outstandings.reporter', '=', 'client')
            ->whereYear('date_in', $year);

        if ($teamId) {
            $query->where('locations.team_id', $teamId);
        }

        $results = $query->groupByRaw('MONTH(date_in)')->get();

        foreach ($results as $result) {
            $data[$result->month - 1] = (int) $result->count;
        }

        return $data;
    }

    protected function getTotalRemoteData($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Reporting::selectRaw('MONTH(reportings.date_visit) as month, COUNT(*) as count')
            ->join('outstandings', 'reportings.outstanding_id', '=', 'outstandings.id')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('reportings.work', 'remote')
            ->where('outstandings.reporter', '=', 'client')
            ->whereYear('reportings.date_visit', $year);

        if ($teamId) {
            $query->where('locations.team_id', $teamId);
        }

        $results = $query->groupByRaw('MONTH(reportings.date_visit)')->get();

        foreach ($results as $result) {
            $data[$result->month - 1] = (int) $result->count;
        }

        return $data;
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(['admin', 'head_support']);
    }
}

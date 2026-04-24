<?php

namespace App\Filament\Widgets\Report;

use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\Team;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class MonthlyTeamReportChart extends EChartWidget implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static bool $isDiscovered = false;

    protected static ?string $chartId = 'monthlyTeamReportChart';

    protected static ?string $heading = 'Monthly Team Report Summary';

    protected int | string | array $columnSpan = 'full';

    protected function getContentHeight(): ?int
    {
        return 500;
    }

    public ?string $filterYear = null;
    public ?string $filterTeam = null;

    public function mount(): void
    {
        $this->filterYear = (string) now()->year;
        parent::mount();
    }

    public function updatedFilterYear()
    {
        $this->updateOptions();
    }

    public function updatedFilterTeam()
    {
        $this->updateOptions();
    }

    public function getFiltersTriggerAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('filter')
            ->label('Filters')
            ->icon('heroicon-m-funnel')
            ->color('gray')
            ->button();
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

    public function getFiltersSchema(): \Filament\Schemas\Schema
    {
        return \Filament\Schemas\Schema::make($this)
            ->components([
                Select::make('filterYear')
                    ->label('Year')
                    ->options($this->getYearOptions())
                    ->live(),
                Select::make('filterTeam')
                    ->label('Team')
                    ->placeholder('All Teams')
                    ->options(\App\Models\Team::pluck('name', 'id'))
                    ->live(),
            ]);
    }

    protected function getOptions(): array
    {
        $year = (int) ($this->filterYear ?? now()->year);
        $teamId = $this->filterTeam;

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'legend' => [
                'bottom' => '0',
                'textStyle' => ['fontSize' => 11],
            ],
            'grid' => [
                'top' => '20',
                'left' => '40',
                'right' => '10',
                'bottom' => '60',
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $months,
            ],
            'yAxis' => [
                'type' => 'value',
            ],
            'series' => [
                [
                    'name' => 'Total Outstanding',
                    'type' => 'bar',
                    'data' => $this->getTotalOutstandingPerMonth($year, $teamId),
                    'itemStyle' => ['color' => '#10b981'],
                ],
                [
                    'name' => 'Total Lokasi',
                    'type' => 'bar',
                    'data' => $this->getCumulativeLocationsPerMonth($year, $teamId),
                    'itemStyle' => ['color' => '#f59e0b'],
                ],
                [
                    'name' => 'Total Lokasi Masalah',
                    'type' => 'bar',
                    'data' => $this->getUniqueLocationsPerMonth($year, $teamId),
                    'itemStyle' => ['color' => '#3b82f6'],
                ],
                [
                    'name' => 'Total Visit',
                    'type' => 'bar',
                    'data' => $this->getTotalVisitData($year, $teamId),
                    'itemStyle' => ['color' => '#ef4444'],
                ],
                [
                    'name' => 'Total LPM 1',
                    'type' => 'bar',
                    'data' => $this->getLaporanAwalMasukData($year, $teamId),
                    'itemStyle' => ['color' => '#8b5cf6'],
                ],
                [
                    'name' => 'Total SLA Visit',
                    'type' => 'bar',
                    'data' => $this->getTotalSlaVisitData($year, $teamId),
                    'itemStyle' => ['color' => '#f97316'],
                ],
                [
                    'name' => 'Total Remote',
                    'type' => 'bar',
                    'data' => $this->getTotalRemoteData($year, $teamId),
                    'itemStyle' => ['color' => '#22d3ee'],
                ],
            ],
        ];
    }

    protected function getTotalOutstandingPerMonth($year, $teamId): array
    {
        $data = array_fill(0, 12, 0);

        $query = Outstanding::query()
            ->selectRaw('MONTH(date_in) as month, COUNT(*) as count')
            ->join('locations', 'outstandings.location_id', '=', 'locations.id')
            ->where('outstandings.reporter', '!=', 'preventif')
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
        $data = array_fill(0, 12, 0);

        $previousTotal = Location::query()
            ->whereYear('created_at', '<', $year)
            ->where('status', '!=', 'dismantle');

        if ($teamId) {
            $previousTotal->where('team_id', $teamId);
        }

        $previousTotalCount = $previousTotal->count();

        for ($month = 1; $month <= 12; $month++) {
            $query = Location::query()
                ->whereBetween('created_at', ["$year-01-01", "$year-$month-31"])
                ->where('status', '!=', 'dismantle');

            if ($teamId) {
                $query->where('team_id', $teamId);
            }

            $data[$month - 1] = $previousTotalCount + $query->count();
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
            ->where('outstandings.reporter', '!=', 'preventif')
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
            ->where('outstandings.reporter', '!=', 'preventif')
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
            ->where('outstandings.reporter', '!=', 'preventif')
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
            ->where('outstandings.reporter', '!=', 'preventif')
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
            ->where('outstandings.reporter', '!=', 'preventif')
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

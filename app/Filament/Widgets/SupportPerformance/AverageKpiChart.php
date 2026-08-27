<?php

namespace App\Filament\Widgets\SupportPerformance;

use App\Models\Reporting;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AverageKpiChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Average KPI Trend';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth()->toDateString();
        $teamId = $this->filters['team_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        // Rentang panjang dipadatkan supaya tidak jadi ratusan titik.
        $days = $start->diffInDays($end) + 1;
        [$unit, $sqlFormat, $labelFormat] = match (true) {
            $days > 180 => ['month', '%Y-%m', 'M Y'],
            $days > 31 => ['week', '%x-W%v', 'd M'],
            default => ['day', '%Y-%m-%d', 'M d'],
        };

        $query = Reporting::query()
            ->reported()
            ->whereBetween('date_visit', [$start->toDateString(), $end->toDateString()]);

        if ($teamId) {
            $query->whereHas('users', fn (Builder $q) => $q->where('users.team_id', $teamId));
        }

        // Satu query untuk seluruh rentang; sebelumnya satu query per hari.
        $averages = $query
            ->selectRaw("DATE_FORMAT(date_visit, '{$sqlFormat}') as bucket, AVG(score) as avg_score")
            ->groupBy('bucket')
            ->pluck('avg_score', 'bucket');

        $labels = [];
        $data = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $bucket = match ($unit) {
                'month' => $cursor->format('Y-m'),
                'week' => $cursor->format('o').'-W'.str_pad((string) $cursor->isoWeek(), 2, '0', STR_PAD_LEFT),
                default => $cursor->toDateString(),
            };

            $labels[] = $cursor->translatedFormat($labelFormat);

            // null (bukan 0) supaya hari tanpa laporan tampil putus, bukan seolah-olah
            // nilainya jatuh ke nol.
            $value = $averages[$bucket] ?? null;
            $data[] = $value === null ? null : round((float) $value, 2);

            $cursor->add($unit, 1);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Average KPI'.match ($unit) {
                        'month' => ' (bulanan)',
                        'week' => ' (mingguan)',
                        default => '',
                    },
                    'data' => $data,
                    'borderColor' => '#10b981',
                    'fill' => false,
                    'spanGaps' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

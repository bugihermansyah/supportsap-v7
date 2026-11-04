<?php

namespace App\Filament\Widgets;

use App\Models\Outstanding;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReportSlaVisitOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    #[Reactive]
    public ?array $tableFilters = null;

    protected function getCachedStats(): array
    {
        return $this->getStats();
    }

    protected function getStats(): array
    {
        $filters = $this->tableFilters ?? [];
        $year = $filters['year']['value'] ?? now()->year;
        $month = $filters['month']['value'] ?? now()->month;
        $reporter = $filters['reporter']['value'] ?? null;
        $team = $filters['team']['value'] ?? null;
        $lpmFilter = $filters['lpm']['value'] ?? null;
        $group = $filters['group']['value'] ?? null;

        $query = Outstanding::query()
            ->where('lpm', true)
            ->whereNotNull('product_id')
            ->whereNotNull('date_visit');

        if ($year) {
            $query->whereYear('date_in', $year);
        }

        if ($month) {
            $query->whereMonth('date_in', $month);
        }

        if ($reporter) {
            $query->where('reporter', $reporter);
        }

        if ($lpmFilter !== null && $lpmFilter !== '') {
            $query->where('lpm', $lpmFilter == '1' || $lpmFilter === true);
        }

        if ($group) {
            $query->whereHas('product', fn ($productQuery) => $productQuery->where('group', $group));
        }

        $user = auth()->user();

        if ($user instanceof User && $user->hasRole('helpdesk') && $team) {
            $query->whereHas('location', fn ($locationQuery) => $locationQuery->where('team_id', $team));
        } elseif ($user instanceof User && $user->hasRole('head_support')) {
            $query->whereHas('location', fn ($locationQuery) => $locationQuery->where('team_id', $user->team_id));
        }

        return [
            Stat::make('Laporan awal masuk (LPM)', number_format($query->count(), 0, ',', '.')),
        ];
    }
}

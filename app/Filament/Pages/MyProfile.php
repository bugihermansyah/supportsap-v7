<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\BorrowRequest;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;

class MyProfile extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.my-profile';

    public $month;
    public $year;

    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function filterSchema(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(2)->schema([
                    \Filament\Forms\Components\Select::make('month')
                        ->hiddenLabel()
                        ->options([
                            'all' => 'All',
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                        ])
                        ->live(),
                    \Filament\Forms\Components\Select::make('year')
                        ->hiddenLabel()
                        ->placeholder('Tahun')
                        ->options(function () {
                            $currentYear = now()->year;
                            return array_combine(range($currentYear - 2, $currentYear), range($currentYear - 2, $currentYear));
                        })
                        ->live(),
                ])
            ]);
    }

    protected function getViewData(): array
    {
        $user = filament()->auth()->user();

        $month = $this->month ?? now()->month;
        $year = $this->year ?? now()->year;

        $totalOutstandings = Outstanding::where('user_id', $user->id)
            ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
            ->whereYear('created_at', $year)
            ->count();

        $totalReportings = Reporting::whereHas('reportingUsers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
        ->whereYear('created_at', $year)
        ->count();

        $avgScore = Reporting::whereHas('reportingUsers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
        ->whereYear('created_at', $year)
        ->avg('score') ?? 0;

        $totalBorrows = BorrowRequest::where('requester_id', $user->id)
            ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
            ->whereYear('created_at', $year)
            ->count();

        $totalOutOfTown = Reporting::whereHas('reportingUsers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereHas('outstanding.location', function ($query) {
            $query->where('area_status', 'out');
        })
        ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
        ->whereYear('created_at', $year)
        ->count();
        
        $recentReportings = Reporting::whereHas('reportingUsers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
            ->whereYear('created_at', $year)
            ->latest()
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'type' => 'reporting',
                    'title' => 'Submitted a report',
                    'description' => $item->location_title,
                    'score' => $item->score,
                    'date' => $item->created_at
                ];
            });
            
        $recentOutstandings = Outstanding::where('user_id', $user->id)
            ->when($month !== 'all', fn ($query) => $query->whereMonth('created_at', $month))
            ->whereYear('created_at', $year)
            ->latest()
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'type' => 'outstanding',
                    'title' => 'Created an outstanding task',
                    'description' => $item->title,
                    'score' => null,
                    'date' => $item->created_at
                ];
            });

        $recentActivities = $recentReportings->concat($recentOutstandings)
            ->sortByDesc('date')
            ->take(5)
            ->values();

        $totalDistance = \App\Models\ReportingUser::where('user_id', $user->id)
            ->whereHas('reporting', function ($query) use ($month, $year) {
                $query->when($month !== 'all', fn ($q) => $q->whereMonth('date_visit', $month))
                      ->whereYear('date_visit', $year);
            })
            ->sum('distance') ?? 0;

        if ($totalDistance >= 1000000) {
            $formattedDistance = round($totalDistance / 1000000, 1) . 'M';
        } elseif ($totalDistance >= 1000) {
            $formattedDistance = round($totalDistance / 1000, 1) . 'K';
        } else {
            $formattedDistance = round($totalDistance, 1);
        }

        $last7DaysDistance = collect(range(0, 6))->map(function ($day) use ($user) {
            $date = now()->subDays($day)->format('Y-m-d');
            $distance = \App\Models\ReportingUser::where('user_id', $user->id)
                ->whereHas('reporting', function ($query) use ($date) {
                    $query->whereDate('date_visit', $date);
                })
                ->sum('distance') ?? 0;
                
            return [
                'date' => now()->subDays($day)->format('d M'),
                'distance' => round($distance, 1),
            ];
        })->reverse()->values();

        return [
            'user' => $user,
            'totalOutstandings' => $totalOutstandings,
            'totalReportings' => $totalReportings,
            'avgScore' => round($avgScore, 2),
            'totalBorrows' => $totalBorrows,
            'totalOutOfTown' => $totalOutOfTown,
            'totalDistance' => $formattedDistance,
            'recentActivities' => $recentActivities,
            'last7DaysDistance' => $last7DaysDistance,
        ];
    }
}

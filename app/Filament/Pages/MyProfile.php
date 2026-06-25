<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\BorrowRequest;

class MyProfile extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.my-profile';

    protected function getViewData(): array
    {
        $user = filament()->auth()->user();

        $totalOutstandings = Outstanding::where('user_id', $user->id)->count();
        $totalReportings = Reporting::where('user_id', $user->id)->count();
        $avgScore = Reporting::where('user_id', $user->id)->avg('score') ?? 0;
        $totalBorrows = BorrowRequest::where('requester_id', $user->id)->count();
        
        $recentReportings = Reporting::where('user_id', $user->id)
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

        return [
            'user' => $user,
            'totalOutstandings' => $totalOutstandings,
            'totalReportings' => $totalReportings,
            'avgScore' => round($avgScore, 2),
            'totalBorrows' => $totalBorrows,
            'recentActivities' => $recentActivities,
        ];
    }
}

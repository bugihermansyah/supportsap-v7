<?php

namespace App\Filament\Widgets\Admin;

use App\Models\BorrowRequest;
use App\Enums\BorrowRequestStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class BorrowRequestStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'admin']);
    }

    protected function getStats(): array
    {
        $totalRequests = BorrowRequest::count();
        $approvedRequests = BorrowRequest::where('status', BorrowRequestStatus::Submitted)->count();
        $returnedRequests = BorrowRequest::whereIn('status', [
            BorrowRequestStatus::Delivered,
            BorrowRequestStatus::WaitingReturn,
            BorrowRequestStatus::PartiallyReturned
        ])->count();

        return [
            Stat::make('Total Requests', $totalRequests)
                ->description('All time requests')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            Stat::make('New Requests', $approvedRequests)
                ->description('Requests need approval')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Returned Requests', $returnedRequests)
                ->description('Units not yet returned')
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color('warning'),
        ];
    }
}

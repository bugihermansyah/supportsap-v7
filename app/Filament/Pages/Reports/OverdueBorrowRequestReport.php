<?php

namespace App\Filament\Pages\Reports;

use App\Models\BorrowRequest;
use App\Enums\BorrowRequestStatus;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OverdueBorrowRequestReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|\UnitEnum|null $navigationGroup = 'Borrow Reports';
    protected static ?string $title = 'Overdue Borrow Requests';
    protected string $view = "filament.pages.reports.overdue-borrow-request-report";
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BorrowRequest::query()
                    ->whereIn('status', [
                        BorrowRequestStatus::Delivered,
                        BorrowRequestStatus::WaitingReturn,
                        BorrowRequestStatus::PartiallyReturned,
                    ])
                    ->whereHas('logs', function (Builder $query) {
                        $query->where('log_status', BorrowRequestStatus::Delivered)
                              ->where('created_at', '<=', now()->subDays(3));
                    })
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Request ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->label('Requester')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}

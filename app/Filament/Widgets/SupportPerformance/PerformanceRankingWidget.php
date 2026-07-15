<?php

namespace App\Filament\Widgets\SupportPerformance;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\User;
use App\Enums\BorrowRequestStatus;
use App\Enums\OutstandingStatus;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class PerformanceRankingWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $teamId = $this->filters['team_id'] ?? null;
        $userId = $this->filters['user_id'] ?? null;
        $customerId = $this->filters['customer_id'] ?? null;
        $locationId = $this->filters['location_id'] ?? null;

        // Team scoping based on role
        $currentUser = auth()->user();
        if ($currentUser->hasRole('head_support')) {
            $teamId = $currentUser->team_id; // force own team
        }

        return $table
            ->query(
                User::role('support')
                    ->where('status', '!=', 0)
                    ->when($teamId, fn($q) => $q->where('team_id', $teamId))
                    ->when($userId, fn($q) => $q->where('id', $userId))
                    ->withAvg(['reportings as kpi' => function(Builder $query) use ($startDate, $endDate, $locationId, $customerId) {
                        if ($startDate) $query->whereDate('date_visit', '>=', $startDate);
                        if ($endDate) $query->whereDate('date_visit', '<=', $endDate);
                        if ($locationId) $query->whereHas('outstanding', fn($q) => $q->where('location_id', $locationId));
                        if ($customerId) $query->whereHas('outstanding.location', fn($q) => $q->where('customer_id', $customerId));
                    }], 'score')
                    ->withCount(['reportings as reportings_count' => function(Builder $query) use ($startDate, $endDate, $locationId, $customerId) {
                        if ($startDate) $query->whereDate('date_visit', '>=', $startDate);
                        if ($endDate) $query->whereDate('date_visit', '<=', $endDate);
                        if ($locationId) $query->whereHas('outstanding', fn($q) => $q->where('location_id', $locationId));
                        if ($customerId) $query->whereHas('outstanding.location', fn($q) => $q->where('customer_id', $customerId));
                    }])
                    ->withCount(['outstandings as outstandings_count' => function(Builder $query) use ($startDate, $endDate, $locationId, $customerId) {
                        $query->where('status', OutstandingStatus::Open);
                        if ($startDate) $query->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $query->whereDate('created_at', '<=', $endDate);
                        if ($locationId) $query->where('location_id', $locationId);
                        if ($customerId) $query->whereHas('location', fn($q) => $q->where('customer_id', $customerId));
                    }])
                    ->withCount(['borrowRequests as borrows_count' => function(Builder $query) {
                        $query->whereNotIn('status', [BorrowRequestStatus::Returned, BorrowRequestStatus::Cancelled]);
                    }])
                    ->withSum(['reportingUsers as total_distance' => function(Builder $query) use ($startDate, $endDate) {
                        if ($startDate || $endDate) {
                            $query->whereHas('reporting', function($q) use ($startDate, $endDate) {
                                if ($startDate) $q->whereDate('date_visit', '>=', $startDate);
                                if ($endDate) $q->whereDate('date_visit', '<=', $endDate);
                            });
                        }
                    }], 'distance')
            )
            ->defaultSort('kpi', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('Rank')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Engineer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kpi')
                    ->label('KPI')
                    ->numeric(1)
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state >= 100 ? 'success' : ($state >= 90 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('reportings_count')
                    ->label('Reporting')
                    ->sortable(),
                Tables\Columns\TextColumn::make('outstandings_count')
                    ->label('Outstanding')
                    ->sortable(),
                Tables\Columns\TextColumn::make('borrows_count')
                    ->label('Borrow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_distance')
                    ->label('Distance (KM)')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sla')
                    ->label('SLA')
                    ->default('100%') // Mockup for now
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->default('Online') // Mockup
                    ->badge()
                    ->color('success'),
            ])
            ->actions([
                Action::make('view_profile')
                    ->label('View Profile')
                    ->icon('heroicon-m-user')
                    ->url(fn (User $record): string => \App\Filament\Pages\UserKpiReport::getUrl(['user_id' => $record->id])),
            ]);
    }
}

<?php

namespace App\Filament\Widgets\HeadSupport;

use App\Models\Outstanding;
use App\Models\Reporting;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class HeadSupportOpenOutstanding extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();

                return Reporting::query()
                    ->whereRaw('reportings.created_at = (SELECT MAX(r2.created_at) FROM reportings r2 WHERE r2.outstanding_id = reportings.outstanding_id)')
                    ->whereHas('outstanding', function ($query) use ($user) {
                        $query->where('status', \App\Enums\OutstandingStatus::Open)
                              ->where('outstandings.is_implement', 0)
                              ->where('outstandings.date_in', '<=', now()->subDays(3))
                              ->whereIn('reporter', ['client','support']);

                        if ($user && $user->team_id && ! $user->hasRole('admin')) {
                            $query->whereHas('location', function ($q) use ($user) {
                                $q->where('team_id', $user->team_id);
                            });
                        }
                    });
            })
            ->columns([
                TextColumn::make('outstanding.location.team.name')
                    ->label('Team')
                    ->sortable()
                    ->visible(fn() => auth()->user()?->hasAnyRole(['manager','helpdesk']))
                    ->searchable(),
                TextColumn::make('outstanding.location.name')
                    ->label('Location')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('outstanding.title')
                    ->label('Outstanding')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('outstanding.date_in')
                    ->label('Report client')
                    ->sortable()
                    ->date('d M Y'),
                TextColumn::make('since')
                    ->label('Since (Days)')
                    ->state(function ($record) {
                        if (! $record->outstanding?->date_in) {
                            return '-';
                        }

                        return (int) round(now()->diffInDays($record->outstanding->date_in, false));
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('outstandings.date_in', $direction);
                    })
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 7 => 'danger',
                        $state >= 3 => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => is_numeric($state)
                        ? ($state >= 0 ? "{$state} days left" : abs($state) . ' days ago')
                        : $state),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\ReportStatus ? $state->getLabel() : (\App\Enums\ReportStatus::tryFrom($state)?->getLabel() ?? $state))
                    ->color(fn ($state) => $state instanceof \App\Enums\ReportStatus ? $state->getColor() : (\App\Enums\ReportStatus::tryFrom($state)?->getColor() ?? 'gray'))
                    ->icon(fn ($state) => $state instanceof \App\Enums\ReportStatus ? $state->getIcon() : \App\Enums\ReportStatus::tryFrom($state)?->getIcon()),
                TextColumn::make('revisit')
                    ->label('Revisit')
                    ->sortable()
                    ->date('d M Y'),
                TextColumn::make('revisit_diff')
                    ->label('Revisit In')
                    ->state(function ($record) {
                        if (! $record->revisit) {
                            return '-';
                        }

                        return (int) round(now()->diffInDays($record->revisit, false)); // false = arah positif/negatif
                    })
                    ->badge()
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('reportings.revisit', $direction);
                    })
                    ->color(fn ($state) => match (true) {
                        $state < 0 => 'danger',      // sudah lewat
                        $state == 0 => 'warning',    // hari ini
                        $state <= 3 => 'info',       // mendekati
                        default => 'success',        // masih lama
                    })
                    ->formatStateUsing(fn ($state) => is_numeric($state)
                        ? ($state >= 0 ? "{$state} days left" : abs($state) . ' days ago')
                        : $state),
            ])
            ->recordUrl(fn ($record) => route('filament.admin.resources.outstandings.edit', ['record' => $record->outstanding->id]))
            ->filters([
                SelectFilter::make('reportings.status')
                    ->label('Status')
                    ->options([
                        0 => 'Pending SAP',
                        2 => 'Pending Client',
                        3 => 'Temporary',
                        4 => 'Monitoring',
                    ]),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['head_support', 'manager','helpdesk']);
    }
}

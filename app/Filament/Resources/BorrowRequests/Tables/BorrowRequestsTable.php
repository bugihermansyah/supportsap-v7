<?php

namespace App\Filament\Resources\BorrowRequests\Tables;

use App\Enums\BorrowRequestStatus;
use App\Enums\BorrowRequestType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BorrowRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                $user = auth()->user();
                if ($user) {
                    if ($user->hasRole('support') && !$user->hasRole('head_support')) {
                        $query->where('requester_id', $user->id);
                    } elseif ($user->hasRole('head_support')) {
                        $query->where(function ($q) use ($user) {
                            $q->whereHas('location', function ($locQ) use ($user) {
                                $locQ->where('team_id', $user->team_id);
                            })->orWhere(function ($q2) use ($user) {
                                $q2->whereHas('location', function ($locQ) {
                                    $locQ->where('area_status', 'out');
                                })->whereHas('requester', function ($reqQ) use ($user) {
                                    $reqQ->where('team_id', $user->team_id);
                                });
                            });
                        });
                    }
                }
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested Date')
                    ->searchable()
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('rp_no')
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.full_name')
                    ->limit(22)
                    ->tooltip(fn ($record) => $record->location?->full_name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('send_no')
                    ->label('ReqKRM'),
                TextColumn::make('take_no')
                    ->label('ReqAMB'),
                TextColumn::make('log_status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('log_at')
                    ->date('d M Y')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            
            ->filters([
                SelectFilter::make('requester_id')
                    ->label('Requester')
                    ->relationship('requester', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => $query->role(['head_support', 'support'])->where('status', 1))
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'super_admin'])),
                SelectFilter::make('location')
                    ->label('Location')
                    ->relationship('location', 'name', function (\Illuminate\Database\Eloquent\Builder $query) {
                        $user = auth()->user();
                        if (! $user?->hasAnyRole(['admin', 'super_admin'])) {
                            $query->where('team_id', $user?->team_id);
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('request_type')
                    ->label('Request Type')
                    ->options(
                        collect(BorrowRequestType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->getLabel()])
                    ),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        collect(BorrowRequestStatus::cases())
                            ->filter(fn ($status) => in_array($status, [
                                BorrowRequestStatus::Submitted,
                                BorrowRequestStatus::Approved,
                                BorrowRequestStatus::Rejected,
                                BorrowRequestStatus::WaitingReturn,
                                BorrowRequestStatus::PartiallyReturned,
                                BorrowRequestStatus::Returned,
                                BorrowRequestStatus::Cancelled,
                            ]))
                            ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])
                    ),
                SelectFilter::make('log_status')
                    ->label('Log Status')
                    ->options(
                        collect(BorrowRequestStatus::cases())
                            ->filter(fn ($status) => in_array($status, [
                                BorrowRequestStatus::DeliveryScheduled,
                                BorrowRequestStatus::Delivered,
                                BorrowRequestStatus::PickupScheduled,
                                BorrowRequestStatus::PickedUp,
                            ]))
                            ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])
                    ),
                Filter::make('date_from')
                    ->schema([
                        DatePicker::make('created_from')
                            ->label('Created From')
                            ->default(now()->subMonth()),
                    ])
                    ->query(function ($query, $data) {
                        if (isset($data['created_from'])) {
                            $query->whereDate('created_at', '>=', $data['created_from']);
                        }
                    }),
                Filter::make('date_to')
                    ->schema([
                        DatePicker::make('created_to')
                            ->label('Created To')
                            ->default(now()),
                    ])
                    ->query(function ($query, $data) {
                        if (isset($data['created_to'])) {
                            $query->whereDate('created_at', '<=', $data['created_to']);
                        }
                    }),
            ], layout: FiltersLayout::Modal)
            ->defaultSort(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderByRaw("CASE WHEN status IN ('submitted', 'waiting_return') THEN 0 ELSE 1 END")->orderBy('created_at', 'desc'))
            ->persistFiltersInSession()
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}

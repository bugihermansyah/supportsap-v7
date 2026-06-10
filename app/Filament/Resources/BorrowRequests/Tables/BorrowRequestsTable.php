<?php

namespace App\Filament\Resources\BorrowRequests\Tables;

use App\Enums\BorrowRequestStatus;
use App\Enums\LogStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
            ->columns([
                TextColumn::make('created_at')
                    ->label('Requested Date')
                    ->searchable()
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('rp_no')
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
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
                    ->relationship('requester', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('location')
                    ->label('Location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
                // SelectFilter::make('warehouse')
                //     ->label('Warehouse')
                //     ->relationship('warehouse', 'name')
                //     ->searchable()
                //     ->preload(),
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
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->filtersFormColumns(6)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}

<?php

namespace App\Filament\Resources\StockRequests\Tables;

use App\Enums\BorrowRequestType;
use App\Enums\StockRequestStatus;
use App\Filament\Resources\StockRequests\StockRequestResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('No.')
                    ->prefix('SR-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.full_name')
                    ->label('Lokasi')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('units_count')
                    ->label('Unit')
                    ->counts('units')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable(),
                TextColumn::make('approved_at')
                    ->label('Approve')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('released_at')
                    ->label('Keluar Gudang')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('requester_id')
                    ->label('Requester')
                    ->relationship(
                        'requester',
                        'name',
                        fn (Builder $query) => $query->whereHas(
                            'roles',
                            fn (Builder $roles) => $roles->whereIn('name', ['head_support', 'support']),
                        )->where('status', 1),
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => StockRequestResource::isManager()),
                SelectFilter::make('status')
                    ->options(StockRequestStatus::class)
                    ->multiple(),
                SelectFilter::make('request_type')
                    ->label('Jenis')
                    ->options(BorrowRequestType::class),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                SelectFilter::make('location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'name', function (Builder $query) {
                        $user = auth()->user();

                        if ($user && ! StockRequestResource::isManager()) {
                            $query->where('team_id', $user->team_id);
                        }

                        return $query;
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => StockRequestResource::isManager()),
            ]);
    }
}

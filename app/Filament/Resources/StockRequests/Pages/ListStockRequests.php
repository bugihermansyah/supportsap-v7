<?php

namespace App\Filament\Resources\StockRequests\Pages;

use App\Enums\StockRequestStatus;
use App\Filament\Resources\StockRequests\StockRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockRequests extends ListRecords
{
    protected static string $resource = StockRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Tab alur kerja gudang — hanya berguna untuk pengelola, mengikuti ListBorrowRequests. */
    public function getTabs(): array
    {
        if (! StockRequestResource::isManager()) {
            return [];
        }

        $counter = fn (StockRequestStatus $status): int => static::getResource()::getEloquentQuery()
            ->where('status', $status)
            ->count();

        return [
            'Perlu Approve' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StockRequestStatus::Submitted))
                ->badge(fn (): int => $counter(StockRequestStatus::Submitted)),
            'Siap Keluar' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StockRequestStatus::Approved))
                ->badge(fn (): int => $counter(StockRequestStatus::Approved)),
            'Di Lokasi' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    StockRequestStatus::Released,
                    StockRequestStatus::PartiallyReturned,
                ])),
            'all' => Tab::make(),
        ];
    }
}

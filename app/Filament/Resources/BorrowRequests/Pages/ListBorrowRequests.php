<?php

namespace App\Filament\Resources\BorrowRequests\Pages;

use App\Enums\BorrowRequestStatus;
use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use App\Models\BorrowRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBorrowRequests extends ListRecords
{
    protected static string $resource = BorrowRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        if (! auth()->user()?->hasRole(['super_admin', 'admin'])) {
            return [];
        }

        return [
            'New Request' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', BorrowRequestStatus::Submitted))
                ->badge(static fn(): int => BorrowRequest::query()->where('status', BorrowRequestStatus::Submitted)->count()),
            'Waiting Return' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', BorrowRequestStatus::WaitingReturn))
                ->badge(static fn(): int => BorrowRequest::query()->where('status', BorrowRequestStatus::WaitingReturn)->count()),
            'all' => Tab::make(),
        ];
    }
}

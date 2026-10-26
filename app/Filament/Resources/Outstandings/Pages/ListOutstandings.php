<?php

namespace App\Filament\Resources\Outstandings\Pages;

use App\Enums\OutstandingStatus;
use App\Filament\Resources\Outstandings\OutstandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOutstandings extends ListRecords
{
    protected static string $resource = OutstandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        if (! $user?->hasRole('support')) {
            return [];
        }

        return [
            'all' => Tab::make('All Outstanding'),
            'my_outstanding' => Tab::make('My Outstanding')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', OutstandingStatus::Open)
                    ->whereHas(
                        'location',
                        fn (Builder $locationQuery): Builder => $locationQuery->where('user_id', $user->id),
                    )),
        ];
    }
}

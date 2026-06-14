<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $isSuperAdmin = auth()->user()?->hasRole('super_admin');

        $tabs = [
            'all' => Tab::make('All')
                ->modifyQueryUsing(function (Builder $query) use ($isSuperAdmin) {
                    $query->where('id', '!=', 'system');
                    if (!$isSuperAdmin) {
                        $query->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', 'super_admin'));
                    }
                }),
        ];

        $roles = Role::orderBy('name')->pluck('name')->toArray();

        foreach ($roles as $roleName) {
            // Tab super_admin hanya untuk super_admin
            if ($roleName === 'super_admin' && !$isSuperAdmin) {
                continue;
            }

            $tabs[$roleName] = Tab::make(ucwords(str_replace('_', ' ', $roleName)))
                ->modifyQueryUsing(function (Builder $query) use ($roleName, $isSuperAdmin) {
                    $query->where('id', '!=', 'system')
                        ->whereHas('roles', fn (Builder $q) => $q->where('name', $roleName));

                    if (!$isSuperAdmin) {
                        $query->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', 'super_admin'));
                    }
                });
        }

        return $tabs;
    }
}

<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Resources\Teams\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeams extends ListRecords
{
    protected static string $resource = TeamResource::class;

    public function mount(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user && $user->hasRole('head_support')) {
            if ($user->team_id) {
                redirect(TeamResource::getUrl('edit', ['record' => $user->team_id]));
                return;
            } else {
                abort(403, 'Anda belum memiliki team.');
            }
        }
        
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

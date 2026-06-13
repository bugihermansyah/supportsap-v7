<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Resources\Teams\TeamResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    public function mount(int | string $record): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user && $user->hasRole('head_support')) {
            if ($user->team_id != $record) {
                abort(403, 'Anda tidak memiliki akses ke team ini.');
            }
        }
        
        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }
}

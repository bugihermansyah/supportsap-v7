<?php

namespace App\Filament\Resources\BorrowRequests\Pages;

use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class CreateBorrowRequest extends CreateRecord
{
    protected static string $resource = BorrowRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requester_id'] = auth()->id();

        if (isset($data['request_type']) && $data['request_type'] === 'service_pull') {
            $data['borrow_type'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $log = new \App\Models\BorrowRequestLog();
        $log->borrow_request_id = $this->record->id;
        $log->action_by = auth()->id();
        $log->action = 'submitted';
        
        // Snapshot the units
        $log->details = $this->record->units->map(function ($unit) {
            return [
                'unit_id' => $unit->unit_id,
                'name' => $unit->unit->name ?? 'Unknown',
                'qty' => $unit->qty,
            ];
        })->toArray();
        
        $log->save();

        // Notify
        $borrowRequest = $this->record;

        $admins = \App\Models\User::role('admin')->get();

        Notification::make()
            ->title('New Borrow Request')
            ->icon(Heroicon::QueueList)
            ->body("{$borrowRequest->requester?->name} requested {$borrowRequest->units->count()} units to {$borrowRequest->location?->name}.")
            ->actions([
                Action::make('View')
                    ->url(BorrowRequestResource::getUrl('edit', ['record' => $borrowRequest]))
                    ->button()
                    ->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }
}

<?php

namespace App\Filament\Resources\BorrowRequests\Pages;

use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBorrowRequest extends CreateRecord
{
    protected static string $resource = BorrowRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requester_id'] = auth()->id();

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
    }
}

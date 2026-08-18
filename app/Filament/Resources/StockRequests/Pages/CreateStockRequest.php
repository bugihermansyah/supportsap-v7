<?php

namespace App\Filament\Resources\StockRequests\Pages;

use App\Enums\StockRequestStatus;
use App\Filament\Resources\StockRequests\StockRequestResource;
use App\Models\StockRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateStockRequest extends CreateRecord
{
    protected static string $resource = StockRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requester_id'] = auth()->id();
        $data['status'] = StockRequestStatus::Submitted->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $request = $this->record;

        if (! $request instanceof StockRequest) {
            return;
        }

        // Pengelola gudang internal — merekalah yang approve, bukan head_support.
        $approvers = User::role(['admin', 'helpdesk'])->where('status', 1)->get();

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Stock Request baru')
            ->icon(Heroicon::ArchiveBox)
            ->body("{$request->requester?->name} meminta {$request->units->count()} unit dari {$request->warehouse?->name} untuk {$request->location?->name}.")
            ->actions([
                Action::make('View')
                    ->url(StockRequestResource::getUrl('edit', ['record' => $request]))
                    ->button()
                    ->markAsRead(),
            ])
            ->sendToDatabase($approvers);
    }
}

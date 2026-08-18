<?php

namespace App\Filament\Resources\StockRequests\Pages;

use App\Enums\StockRequestStatus;
use App\Filament\Resources\StockRequests\StockRequestResource;
use App\Models\StockRequest;
use App\Models\StockRequestUnit;
use App\Services\StockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

class EditStockRequest extends EditRecord
{
    protected static string $resource = StockRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->approveAction(),
            $this->rejectAction(),
            $this->releaseAction(),
            $this->returnAction(),
            $this->cancelAction(),
            DeleteAction::make()
                ->visible(fn (StockRequest $record): bool => StockRequestResource::isManager()
                    && ! $record->status->holdsStock()),
        ];
    }

    /** Tahap 1: stok dibooking (qty_available turun, qty_borrowed naik). */
    protected function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve & Booking Stok')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Stok akan langsung dibooking dari gudang setelah disetujui.')
            ->visible(fn (StockRequest $record): bool => $record->status === StockRequestStatus::Submitted
                && StockRequestResource::isManager())
            ->action(function (StockRequest $record, StockService $stock) {
                try {
                    $stock->book($record);
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('Approve gagal')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $this->notifyRequester($record, 'Stock Request disetujui', 'Stok sudah dibooking dan siap diambil.');

                Notification::make()
                    ->title('Stok berhasil dibooking')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            });
    }

    /** Tahap 2: unit fisik keluar gudang. Jumlah stok tidak berubah, hanya dicatat. */
    protected function releaseAction(): Action
    {
        return Action::make('release')
            ->label('Unit Keluar Gudang')
            ->icon(Heroicon::Truck)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (StockRequest $record): bool => $record->status === StockRequestStatus::Approved
                && StockRequestResource::isManager())
            ->action(function (StockRequest $record, StockService $stock) {
                $stock->release($record);

                Notification::make()
                    ->title('Unit tercatat keluar gudang')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            });
    }

    protected function returnAction(): Action
    {
        return Action::make('return')
            ->label('Terima Pengembalian')
            ->icon(Heroicon::ArrowUturnLeft)
            ->color('warning')
            ->visible(fn (StockRequest $record): bool => StockRequestResource::isManager()
                && in_array($record->status, [
                    StockRequestStatus::Released,
                    StockRequestStatus::PartiallyReturned,
                ], true))
            ->schema(fn (StockRequest $record): array => $record->units
                ->map(fn (StockRequestUnit $line) => TextInput::make("returns.{$line->id}")
                    ->label(($line->unit->name ?? $line->unit_id)." — sisa {$line->outstandingQty()} dari {$line->qty}")
                    ->numeric()
                    ->minValue(0)
                    ->maxValue($line->outstandingQty())
                    ->default($line->outstandingQty()))
                ->all())
            ->action(function (StockRequest $record, array $data, StockService $stock) {
                $quantities = collect($data['returns'] ?? [])
                    ->map(fn ($qty) => (int) $qty)
                    ->filter(fn (int $qty) => $qty > 0)
                    ->all();

                if (empty($quantities)) {
                    Notification::make()
                        ->title('Tidak ada unit yang dikembalikan')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $stock->returnUnits($record, $quantities);
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('Pengembalian gagal')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Pengembalian tercatat, stok dikembalikan')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            });
    }

    protected function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (StockRequest $record): bool => $record->status === StockRequestStatus::Submitted
                && StockRequestResource::isManager())
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (StockRequest $record, array $data) {
                $record->update([
                    'status' => StockRequestStatus::Rejected,
                    'note' => trim(($record->note ? $record->note."\n" : '').'Ditolak: '.$data['reason']),
                ]);

                $this->notifyRequester($record, 'Stock Request ditolak', $data['reason']);

                Notification::make()->title('Request ditolak')->success()->send();

                $this->refreshFormData(['status', 'note']);
            });
    }

    /**
     * Batal. Jika stok sudah dibooking, booking dilepas dulu supaya
     * qty_available kembali dan tidak ada stok yang menggantung.
     */
    protected function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::NoSymbol)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn (StockRequest $record): string => $record->status->holdsStock()
                ? 'Booking stok akan dilepas dan qty tersedia dikembalikan.'
                : 'Request akan dibatalkan.')
            // Requester boleh membatalkan requestnya sendiri selama belum diproses;
            // setelah stok dibooking hanya pengelola gudang yang boleh melepasnya.
            ->visible(function (StockRequest $record): bool {
                if (StockRequestResource::isManager()) {
                    return in_array($record->status, [
                        StockRequestStatus::Submitted,
                        StockRequestStatus::Approved,
                        StockRequestStatus::Released,
                        StockRequestStatus::PartiallyReturned,
                    ], true);
                }

                return $record->status === StockRequestStatus::Submitted
                    && $record->requester_id === auth()->id();
            })
            ->action(function (StockRequest $record, StockService $stock) {
                if ($record->status->holdsStock()) {
                    $stock->releaseBooking($record, StockRequestStatus::Cancelled);
                } else {
                    $record->update(['status' => StockRequestStatus::Cancelled]);
                }

                Notification::make()->title('Request dibatalkan')->success()->send();

                $this->refreshFormData(['status']);
            });
    }

    private function notifyRequester(StockRequest $record, string $title, string $body): void
    {
        if (! $record->requester) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('View')
                    ->url(StockRequestResource::getUrl('edit', ['record' => $record]))
                    ->button()
                    ->markAsRead(),
            ])
            ->sendToDatabase($record->requester);
    }
}

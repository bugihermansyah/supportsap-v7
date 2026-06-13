<?php

namespace App\Filament\Resources\BorrowRequests\Pages;

use App\Enums\BorrowRequestStatus;
use App\Enums\BorrowRequestType;
use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBorrowRequest extends EditRecord
{
    protected static string $resource = BorrowRequestResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return '' . static::getResource()::getRecordTitle($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn($record) => auth()->user()?->hasRole(['super_admin', 'admin']) && $record->status?->value === 'submitted' && $record->request_type !== BorrowRequestType::PullRequest)
                ->requiresConfirmation()
                ->schema([
                    TextInput::make('send_no')
                        ->label('Req KRM')
                        ->required()
                        ->placeholder('REQKRM/9999/999')
                        ->mask('REQKRM/9999/999')
                        ->regex('/^REQKRM\/\d{4}\/\d{3}$/')
                        ->validationMessages([
                            'regex' => 'Format harus REQKRM/9999/999.',
                        ]),
                ])
                ->action(function (array $data, BorrowRequest $record) {
                    $log = new BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = 'approved';
                    $log->details = $record->units->map(function ($unit) {
                        return [
                            'unit_id' => $unit->unit_id,
                            'name' => $unit->unit->name ?? 'Unknown',
                            'qty' => $unit->qty,
                        ];
                    })->toArray();
                    $log->save();

                    $record->update([
                        'send_no' => $data['send_no'],
                        'status' => 'approved',
                    ]);

                    if ($record->requester) {
                        Notification::make()
                            ->title('Request Approved')
                            ->success()
                            ->body("Your request {$record->rp_no} has been approved to {$record->location?->name}.")
                            ->actions([
                                Action::make('View')
                                    ->url(BorrowRequestResource::getUrl('edit', ['record' => $record]))
                                    ->button()
                                    ->markAsRead(),
                            ])
                            ->sendToDatabase($record->requester);
                    }

                    return redirect(request()->header('Referer'));
                }),

            Action::make('request_return')
                ->label('Request Return')
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn($record) => in_array($record->status?->value, ['approved', 'partially_returned']))
                ->modalHeading('Request Return Unit')
                ->modalDescription('Pilih barang dan sesuaikan quantity yang akan dikembalikan saat ini.')
                ->schema([
                    Textarea::make('pickup_address')
                        ->label('Pickup Address')
                        ->required(),
                    TextInput::make('pickup_contact')
                        ->label('Pickup Contact')
                        ->required(),
                    Repeater::make('return_items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Name'),
                            TableColumn::make('Qty')
                                ->width('70px'),
                            TableColumn::make('R.Q')
                                ->width('70px'),
                            TableColumn::make('Q.T.R')
                                ->width('70px'),
                            TableColumn::make('Claim')
                                ->width('80px'),
                            TableColumn::make('Condition'),
                        ])
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->label('Unit')
                                ->disabled(),
                            TextInput::make('qty')
                                ->label('Qty Borrow')
                                ->disabled()
                                ->numeric(),
                            TextInput::make('returned_qty_before')
                                ->label('Returned Qty')
                                ->disabled()
                                ->numeric(),
                            TextInput::make('return_qty')
                                ->label('Qty To Return')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(fn($get) => $get('qty') - $get('returned_qty_before')),
                            Checkbox::make('is_claim')
                                ->label('Claim'),
                            TextInput::make('damage')
                                ->label('Condition')
                                ->required(),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        // ->columns(4)
                ])
                ->fillForm(function (\App\Models\BorrowRequest $record) {
                    return [
                        'pickup_address' => $record->shipping_address,
                        'pickup_contact' => $record->shipping_contact,
                        'return_items' => $record->units->map(function ($unit) {
                            return [
                                'id' => $unit->id,
                                'name' => $unit->unit->name ?? 'Unknown',
                                'qty' => $unit->qty,
                                'returned_qty_before' => $unit->returned_qty ?? 0,
                                'return_qty' => max(0, $unit->qty - ($unit->returned_qty ?? 0)),
                                'is_claim' => $unit->is_claim,
                                'damage' => $unit->damage,
                            ];
                        })->toArray()
                    ];
                })
                ->action(function (array $data, \App\Models\BorrowRequest $record) {
                    $log = new \App\Models\BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = 'waiting_return';
                    
                    $logDetails = [];
                    foreach ($data['return_items'] as $item) {
                        if ($item['return_qty'] > 0) {
                            $unitModel = \App\Models\BorrowRequestUnit::with('unit')->find($item['id']);
                            if ($unitModel) {
                                if (isset($item['is_claim'])) $unitModel->is_claim = $item['is_claim'];
                                if (isset($item['damage'])) $unitModel->damage = $item['damage'];
                                $unitModel->save();
                            }

                            $logDetails[] = [
                                'unit_id' => $unitModel?->unit_id,
                                'name' => $unitModel?->unit?->name ?? 'Unknown',
                                'qty' => $item['return_qty'],
                                'damage' => $item['damage'] ?? null,
                                'is_claim' => $item['is_claim'] ?? null,
                            ];
                        }
                    }
                    $log->details = $logDetails;
                    $log->save();
                    
                    $record->update([
                        'status' => 'waiting_return',
                        'pickup_address' => $data['pickup_address'] ?? null,
                        'pickup_contact' => $data['pickup_contact'] ?? null,
                    ]);
                    
                    $admins = \App\Models\User::role('admin')->get();
                    \Filament\Notifications\Notification::make()
                        ->title('Return Requested')
                        ->warning()
                        ->body("Return request has been submitted for {$record->location?->name}.")
                        ->actions([
                            Action::make('View')
                                ->url(BorrowRequestResource::getUrl('edit', ['record' => $record]))
                                ->button()
                                ->markAsRead(),
                        ])
                        ->sendToDatabase($admins);
                    
                    return redirect(request()->header('Referer'));
                }),

            Action::make('approve_return')
                ->label('Approve Return')
                ->color('info')
                ->icon('heroicon-o-check-badge')
                ->visible(fn($record) => in_array($record->status?->value, ['waiting_return']) || ($record->request_type === BorrowRequestType::PullRequest && $record->status?->value === 'submitted'))
                ->modalHeading('Approve Pengembalian')
                ->modalDescription('Validasi dan sesuaikan jumlah barang yang dikembalikan.')
                ->schema([
                    Textarea::make('pickup_address')
                        ->label('Pickup Address')
                        ->disabled(),
                    TextInput::make('pickup_contact')
                        ->label('Pickup Contact')
                        ->disabled(),
                    TextInput::make('take_no')
                        ->label('Req AMB')
                        ->required()
                        ->placeholder('REQAMB/9999/999')
                        ->mask('REQAMB/9999/999')
                        ->regex('/^REQAMB\/\d{4}\/\d{3}$/')
                        ->validationMessages([
                            'regex' => 'Format harus REQAMB/9999/999.',
                        ]),
                    Repeater::make('return_items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Name'),
                            TableColumn::make('Qty')
                                ->width('70px'),
                            TableColumn::make('R.Q')
                                ->width('70px'),
                            TableColumn::make('Q.T.R')
                                ->width('70px'),
                            TableColumn::make('Claim')
                                ->width('80px'),
                            TableColumn::make('Condition'),
                        ])
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->label('Unit')
                                ->disabled(),
                            TextInput::make('qty')
                                ->label('Qty Dipinjam')
                                ->disabled()
                                ->numeric(),
                            TextInput::make('returned_qty_before')
                                ->label('Returned Qty')
                                ->disabled()
                                ->numeric(),
                            TextInput::make('return_qty')
                                ->label('Qty To Return')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(fn($get) => $get('qty') - $get('returned_qty_before')),
                            Checkbox::make('is_claim')
                                ->label('Claim'),
                            TextInput::make('damage')
                                ->label('Condition')
                                ->disabled()
                                ->dehydrated(),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        // ->columns(4)
                ])
                ->fillForm(function (\App\Models\BorrowRequest $record) {
                    // Try to pre-fill with the intended return quantities from 'waiting_return' log
                    $lastLog = \App\Models\BorrowRequestLog::where('borrow_request_id', $record->id)
                        ->where('action', 'waiting_return')
                        ->latest()
                        ->first();
                        
                    $intendedReturns = [];
                    $intendedDamage = [];
                    if ($lastLog && is_array($lastLog->details)) {
                        foreach ($lastLog->details as $detail) {
                            if (isset($detail['unit_id'])) {
                                $intendedReturns[$detail['unit_id']] = $detail['qty'];
                                $intendedDamage[$detail['unit_id']] = $detail['damage'] ?? null;
                            }
                        }
                    }

                    return [
                        'pickup_address' => $record->pickup_address,
                        'pickup_contact' => $record->pickup_contact,
                        'return_items' => $record->units->map(function ($unit) use ($intendedReturns, $intendedDamage) {
                            $intendedQty = $intendedReturns[$unit->unit_id] ?? max(0, $unit->qty - ($unit->returned_qty ?? 0));
                            return [
                                'id' => $unit->id,
                                'name' => $unit->unit->name ?? 'Unknown',
                                'qty' => $unit->qty,
                                'returned_qty_before' => $unit->returned_qty ?? 0,
                                'return_qty' => $intendedQty,
                                'is_claim' => $unit->is_claim,
                                'damage' => $intendedDamage[$unit->unit_id] ?? $unit->damage ?? null,
                            ];
                        })->toArray()
                    ];
                })
                ->action(function (array $data, \App\Models\BorrowRequest $record) {
                    $allReturned = true;
                    
                    foreach ($data['return_items'] as $item) {
                        $unit = \App\Models\BorrowRequestUnit::find($item['id']);
                        if ($unit) {
                            $unit->returned_qty = ($unit->returned_qty ?? 0) + $item['return_qty'];
                            if (isset($item['damage']) && $item['damage'] !== null) {
                                $unit->damage = $item['damage'];
                            }
                            if (isset($item['is_claim'])) {
                                $unit->is_claim = $item['is_claim'];
                            }
                            $unit->save();
                            
                            if ($unit->returned_qty < $unit->qty) {
                                $allReturned = false;
                            }
                        }
                    }

                    $log = new \App\Models\BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = $allReturned ? 'returned' : 'partially_returned';
                    
                    $logDetails = [];
                    foreach ($data['return_items'] as $item) {
                        if ($item['return_qty'] > 0) {
                            $unitModel = \App\Models\BorrowRequestUnit::with('unit')->find($item['id']);
                            $logDetails[] = [
                                'unit_id' => $unitModel?->unit_id,
                                'name' => $unitModel?->unit?->name ?? 'Unknown',
                                'qty' => $item['return_qty'],
                                'damage' => $item['damage'] ?? null,
                                'is_claim' => $item['is_claim'] ?? null,
                            ];
                        }
                    }
                    $log->details = $logDetails;
                    $log->save();
                    
                    $record->update([
                        'status' => $allReturned ? 'returned' : 'partially_returned',
                        'take_no' => $data['take_no'],
                    ]);

                    if ($record->requester) {
                        Notification::make()
                            ->title('Request Return')
                            ->warning()
                            ->body("Return request has been approved for {$record->location?->name}.")
                            ->actions([
                                Action::make('View')
                                    ->url(BorrowRequestResource::getUrl('edit', ['record' => $record]))
                                    ->button()
                                    ->markAsRead(),
                            ])
                            ->sendToDatabase($record->requester);
                    }
                    return redirect(request()->header('Referer'));
                }),

            Action::make('cancel')
                ->label('Cancel')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn($record) => $record->status?->value === 'submitted')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('note')
                        ->label('Reason for Cancel')
                        ->required(),
                ])
                ->action(function (array $data, \App\Models\BorrowRequest $record) {
                    $log = new \App\Models\BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = 'cancelled';
                    $log->note = $data['note'];
                    $log->details = $record->units->map(function ($unit) {
                        return [
                            'unit_id' => $unit->unit_id,
                            'name' => $unit->unit->name ?? 'Unknown',
                            'qty' => $unit->qty,
                        ];
                    })->toArray();
                    $log->save();
                    
                    $record->update(['status' => 'cancelled']);

                    if ($record->requester) {
                        Notification::make()
                            ->title('Request Cancelled')
                            ->danger()
                            ->body("Your request to {$record->location?->name} was cancelled.")
                            ->actions([
                                Action::make('View')
                                    ->url(BorrowRequestResource::getUrl('edit', ['record' => $record])),
                            ])
                            ->sendToDatabase($record->requester);
                    }
                    
                    return redirect(request()->header('Referer'));
                }),

            Action::make('manual_log')
                ->label('Add Manual History')
                ->color('gray')
                ->icon('heroicon-o-pencil-square')
                ->modalHeading('Add Manual History')
                ->modalDescription('Gunakan fitur ini jika log dari email gagal masuk. Ini akan menambahkan log history dan mengubah status request.')
                ->schema([
                    DateTimePicker::make('created_at')
                        ->label('Tanggal')
                        ->default(now())
                        ->required(),
                    Select::make('action')
                        ->label('Action / Status')
                        ->options(
                        collect(BorrowRequestStatus::cases())
                            ->filter(fn ($status) => in_array($status, [
                                BorrowRequestStatus::DeliveryScheduled,
                                BorrowRequestStatus::Delivered,
                                BorrowRequestStatus::PickupScheduled,
                                BorrowRequestStatus::PickedUp,
                            ]))
                            ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])
                    )
                        ->required(),
                    DatePicker::make('date')
                        ->label('Date')
                        ->required(),
                    Textarea::make('note')
                        ->label('Note / Keterangan')
                        ->required(),
                ])
                ->action(function (array $data, BorrowRequest $record) {
                    $log = new BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = $data['action'];
                    $log->date = $data['date'];
                    $log->note = $data['note'];
                    $log->created_at = $data['created_at'];
                    
                    // Copy snapshot details from current units
                    $log->details = $record->units->map(function ($unit) {
                        return [
                            'unit_id' => $unit->unit_id,
                            'name' => $unit->unit->name ?? 'Unknown',
                            'qty' => $unit->qty,
                            'damage' => $unit->damage ?? null,
                        ];
                    })->toArray();
                    $log->save();

                    // Update log status request
                    $record->update([
                        'log_status' => $data['action'],
                        'log_at' => $data['date']
                    ]);
                    
                    return redirect(request()->header('Referer'));
                }),

        ];
    }
}

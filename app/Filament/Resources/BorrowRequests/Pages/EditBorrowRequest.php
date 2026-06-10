<?php

namespace App\Filament\Resources\BorrowRequests\Pages;

use App\Filament\Resources\BorrowRequests\BorrowRequestResource;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestLog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditBorrowRequest extends EditRecord
{
    protected static string $resource = BorrowRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn($record) => $record->status?->value === 'submitted')
                ->requiresConfirmation()
                ->form([
                    TextInput::make('rp_no')
                        ->label('RP No')
                        ->required()
                        ->placeholder('RP-XXXX-XXXXXX')
                        ->regex('/^RP-\d{4}-\d{6}$/')
                        ->validationMessages([
                            'regex' => 'Format harus RP-YYMM-XXXXXX.',
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
                        'rp_no' => $data['rp_no'],
                        'status' => 'approved',
                    ]);

                    return redirect(request()->header('Referer'));
                }),

            \Filament\Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn($record) => $record->status?->value === 'submitted')
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('note')
                        ->label('Reason for Rejection')
                        ->required(),
                ])
                ->action(function (array $data, \App\Models\BorrowRequest $record) {
                    $log = new \App\Models\BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = 'rejected';
                    $log->note = $data['note'];
                    $log->details = $record->units->map(function ($unit) {
                        return [
                            'unit_id' => $unit->unit_id,
                            'name' => $unit->unit->name ?? 'Unknown',
                            'qty' => $unit->qty,
                        ];
                    })->toArray();
                    $log->save();
                    
                    $record->update(['status' => 'rejected']);
                    
                    return redirect(request()->header('Referer'));
                }),

            Action::make('request_return')
                ->label('Request Return')
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn($record) => in_array($record->status?->value, ['approved', 'partially_returned']))
                ->modalHeading('Request Return Unit')
                ->modalDescription('Pilih barang dan sesuaikan quantity yang akan dikembalikan saat ini.')
                ->form([
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
                            TableColumn::make('Qty'),
                            TableColumn::make('Returned Qty'),
                            TableColumn::make('Qty To Return'),
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
                            $logDetails[] = [
                                'unit_id' => $unitModel?->unit_id,
                                'name' => $unitModel?->unit?->name ?? 'Unknown',
                                'qty' => $item['return_qty'],
                                'damage' => $item['damage'] ?? null,
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
                    
                    return redirect(request()->header('Referer'));
                }),

            Action::make('approve_return')
                ->label('Approve Return')
                ->color('info')
                ->icon('heroicon-o-check-badge')
                ->visible(fn($record) => $record->status?->value === 'waiting_return')
                ->modalHeading('Approve Pengembalian')
                ->modalDescription('Validasi dan sesuaikan jumlah barang yang dikembalikan.')
                ->form([
                    Textarea::make('pickup_address')
                        ->label('Pickup Address')
                        ->disabled(),
                    TextInput::make('pickup_contact')
                        ->label('Pickup Contact')
                        ->disabled(),
                    Repeater::make('return_items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Name'),
                            TableColumn::make('Qty'),
                            TableColumn::make('Returned Qty'),
                            TableColumn::make('Qty To Return'),
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
                                'damage' => $intendedDamage[$unit->unit_id] ?? null,
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
                            ];
                        }
                    }
                    $log->details = $logDetails;
                    $log->save();
                    
                    $record->update(['status' => $allReturned ? 'returned' : 'partially_returned']);
                    
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
                        ->required(),
                    Select::make('action')
                        ->label('Action / Status')
                        ->options(\App\Enums\BorrowRequestStatus::class)
                        ->required(),
                    Textarea::make('note')
                        ->label('Note / Keterangan')
                        ->required(),
                ])
                ->action(function (array $data, \App\Models\BorrowRequest $record) {
                    $log = new \App\Models\BorrowRequestLog();
                    $log->borrow_request_id = $record->id;
                    $log->action_by = auth()->id();
                    $log->action = $data['action'];
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

                    // Update status request
                    $record->update(['status' => $data['action']]);
                    
                    return redirect(request()->header('Referer'));
                }),

            // DeleteAction::make(),
        ];
    }
}

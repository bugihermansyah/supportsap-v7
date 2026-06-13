<?php

namespace App\Filament\Resources\BorrowRequests\Schemas;

use App\Enums\BorrowRequestType;
use App\Enums\LocationStatus;
use App\Models\BorrowRequest;
use App\Models\Contract;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class BorrowRequestForm
{
    public static function configure(Schema $schema): Schema
        {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Request')
                            ->hiddenOn('edit')
                            ->columnSpan(1)
                            ->schema([
                                Radio::make('request_type')
                                    ->label('Request Type')
                                    ->options(BorrowRequestType::class)
                                    ->inline()
                                    ->default('replacement')
                                    ->required()
                                    ->live(),
                                Hidden::make('warehouse_id')
                                    ->default(1),
                                Select::make('location_id')
                                    ->label('Location')
                                    ->relationship('location', 'name', function ($query) {
                                        $query->with('company');
                                        $query->where('status', '!=', LocationStatus::InActive);
                                        $user = auth()->user();
                                        if ($user && $user->hasRole(['head_support', 'support'])) {
                                            $query->where(function ($q) use ($user) {
                                                $q->where('team_id', $user->team_id)
                                                  ->orWhere('area_status', 'out');
                                            });
                                        }
                                    })
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $location = Location::with('contactShippings.customer')->find($state);
                                        if ($location) {
                                            $set('shipping_address', $location->name);
                                            $set('pickup_address', $location->name);
                                            
                                            $contacts = $location->contactShippings->map(function ($cs) {
                                                return $cs->customer ? $cs->customer->name_tlp : null;
                                            })->filter()->join(', ');
                                            if ($contacts) {
                                                $set('shipping_contact', $contacts);
                                                $set('pickup_contact', $contacts);
                                            }
                                        }

                                        $defaultProduct = Contract::query()
                                            ->where('location_id', $state)
                                            ->where('is_default', 1)
                                            ->join('products', 'products.id', '=', 'contracts.product_id')
                                            ->select('contracts.id')
                                            ->first();

                                        if ($defaultProduct) {
                                            $set('contract_id', $defaultProduct->id);
                                        } else {
                                            $set('contract_id', null);
                                        }
                                    }),
                                Radio::make('contract_id')
                                    ->label('Produk')
                                    ->columnSpanFull()
                                    ->hiddenLabel()
                                    ->options(fn(Get $get): Collection => Contract::query()
                                        ->where('location_id', $get('location_id'))
                                        ->where('status', 1)
                                        ->join('products', 'products.id', '=', 'contracts.product_id')
                                        ->pluck('products.name', 'contracts.id'))
                                    ->required(),
                                Textarea::make('note')
                                    ->label('Note')
                                    ->rows(2),
                                Textarea::make('shipping_address')
                                    ->label('Shipping Address')
                                    ->rows(2)
                                    ->required()
                                    ->hidden(fn (Get $get) => ($get('request_type') instanceof \App\Enums\BorrowRequestType ? $get('request_type')->value : $get('request_type')) === 'pull_request'),
                                TextInput::make('shipping_contact')
                                    ->label('Shipping Contact')
                                    ->required()
                                    ->hidden(fn (Get $get) => ($get('request_type') instanceof \App\Enums\BorrowRequestType ? $get('request_type')->value : $get('request_type')) === 'pull_request'),
                                Textarea::make('pickup_address')
                                    ->label('Pickup Address')
                                    ->rows(2)
                                    ->required()
                                    ->hidden(fn (Get $get) => ($get('request_type') instanceof \App\Enums\BorrowRequestType ? $get('request_type')->value : $get('request_type')) !== 'pull_request'),
                                TextInput::make('pickup_contact')
                                    ->label('Pickup Contact')
                                    ->required()
                                    ->hidden(fn (Get $get) => ($get('request_type') instanceof \App\Enums\BorrowRequestType ? $get('request_type')->value : $get('request_type')) !== 'pull_request'),
                                
                            ]),
                            
                        Section::make('Info')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('location.full_name')
                                    ->label('Location')
                                    ->copyable()
                                    ->copyMessage('Location Copied!')
                                    ->copyMessageDuration(1500),
                                TextInput::make('rp_no')
                                    ->label('No. RP')
                                    // ->mask('REQKRM/9999/999')
                                    ->placeholder('RP-XXXX-XXXXXX')
                                    ->mask('RP-9999-999999')
                                    ->regex('/^RP-\d{4}-\d{6}$/')
                                    ->copyable(copyMessage: 'RP Copied!', copyMessageDuration: 1500)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?BorrowRequest $record, $state) {
                                        if ($record) {
                                            $record->update(['rp_no' => $state]);
                                            Notification::make()
                                                ->title('No. RP Updated')
                                                ->success()
                                                ->send();
                                        }
                                    }),
                                TextEntry::make('send_no')
                                    ->label('No. KRM')
                                    ->copyable()
                                    ->copyMessage('KRM Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('take_no')
                                    ->label('No. AMB')
                                    ->copyable()
                                    ->copyMessage('AMB Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('requester.name')
                                    ->label('Requester')
                                    ->formatStateUsing(function ($state, ?BorrowRequest $record) {
                                        if (!$record || !$record->requester) return $state;
                                        return "{$state} ({$record->requester->email})";
                                    })
                                    ->copyable()
                                    ->copyableState(fn (?BorrowRequest $record) => $record?->requester?->email ?? '')
                                    ->copyMessage('Email copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('extra_note')
                                    ->label('Extra notes')
                                    ->state(function (?BorrowRequest $record) {
                                        if (!$record) return '-';
                                        
                                        $rpNo = $record->rp_no ?? '';
                                        $locName = $record->location->name ?? '';
                                        $text = trim("{$rpNo} {$locName}");
                                        
                                        if ($record->units) {
                                            foreach ($record->units as $item) {
                                                $unitName = $item->unit->name ?? '';
                                                $qty = $item->qty ?? 0;
                                                $text .= "\n" . trim("{$unitName} {$qty} unit");
                                            }
                                        }
                                        
                                        return $text;
                                    })
                                    ->formatStateUsing(fn ($state) => new \Illuminate\Support\HtmlString('<div style="white-space: pre-wrap;">' . e($state) . '</div>'))
                                    ->copyable()
                                    ->copyableState(fn ($state) => $state)
                                    ->copyMessage('Extra Note Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('seal_format')
                                    ->label('Seal Format')
                                    ->state(function (?BorrowRequest $record) {
                                        if (!$record) return '-';
                                        
                                        $firstUnit = $record->units ? $record->units->first() : null;
                                        $sealValue = $firstUnit ? $firstUnit->seal : null;
                                        try {
                                            $seal = $sealValue ? \Carbon\Carbon::parse($sealValue)->format('d/m/Y') : '';
                                        } catch (\Exception $e) {
                                            $seal = $sealValue ?? '';
                                        }
                                        
                                        $contractId = $record->contract_id;
                                        $contractType = '-';
                                        if ($contractId) {
                                            $contractType = \App\Models\Contract::find($contractId)?->type_contract ?? '-';
                                        }
                                        
                                        $requester = $record->requester->name ?? '';
                                        
                                        return "{$seal}[{$contractType}]{$requester}";
                                    })
                                    ->copyable()
                                    ->copyMessage('Format Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('note')
                                    ->label('Notes')
                                    ->limit(50),
                                TextEntry::make('created_at')
                                    ->dateTime('d M Y H:i:s'),
                            ])
                            ->hidden(fn (?BorrowRequest $record) => $record === null),
  
                        Section::make('Shipping & Pickup')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('shipping_address')
                                    ->label('Shipping Address')
                                    ->placeholder('Not set')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('shipping_contact')
                                    ->label('Shipping Contact')
                                    ->placeholder('Not set')
                                    ->formatStateUsing(function ($state) {
                                        if (!$state) return '-';
                                        $parts = explode('/', $state);
                                        if (count($parts) < 2) return $state;
                                        
                                        $name = trim($parts[0]);
                                        $phone = trim($parts[1]);
                                        
                                        $nameJs = htmlspecialchars(addslashes($name), ENT_QUOTES, 'UTF-8');
                                        $phoneJs = htmlspecialchars(addslashes($phone), ENT_QUOTES, 'UTF-8');
                                        
                                        return new \Illuminate\Support\HtmlString(
                                            '<div x-data="{}">' .
                                            '<span x-on:click="window.navigator.clipboard.writeText(\'' . $nameJs . '\'); $tooltip(\'Name copied\')" class="cursor-pointer hover:text-primary-600 dark:hover:text-primary-400 hover:underline transition" title="Copy name">' . e($name) . '</span>' .
                                            ' / ' .
                                            '<span x-on:click="window.navigator.clipboard.writeText(\'' . $phoneJs . '\'); $tooltip(\'Phone copied\')" class="cursor-pointer hover:text-primary-600 dark:hover:text-primary-400 hover:underline transition" title="Copy phone">' . e($phone) . '</span>' .
                                            '</div>'
                                        );
                                    }),
                                TextEntry::make('pickup_address')
                                    ->label('Pickup Address')
                                    ->placeholder('Not set')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('pickup_contact')
                                    ->label('Pickup Contact')
                                    ->placeholder('Not set')
                                    ->formatStateUsing(function ($state) {
                                        if (!$state) return '-';
                                        $parts = explode('/', $state);
                                        if (count($parts) < 2) return $state;
                                        
                                        $name = trim($parts[0]);
                                        $phone = trim($parts[1]);
                                        
                                        $nameJs = htmlspecialchars(addslashes($name), ENT_QUOTES, 'UTF-8');
                                        $phoneJs = htmlspecialchars(addslashes($phone), ENT_QUOTES, 'UTF-8');
                                        
                                        return new \Illuminate\Support\HtmlString(
                                            '<div x-data="{}">' .
                                            '<span x-on:click="window.navigator.clipboard.writeText(\'' . $nameJs . '\'); $tooltip(\'Name copied\')" class="cursor-pointer hover:text-primary-600 dark:hover:text-primary-400 hover:underline transition" title="Copy name">' . e($name) . '</span>' .
                                            ' / ' .
                                            '<span x-on:click="window.navigator.clipboard.writeText(\'' . $phoneJs . '\'); $tooltip(\'Phone copied\')" class="cursor-pointer hover:text-primary-600 dark:hover:text-primary-400 hover:underline transition" title="Copy phone">' . e($phone) . '</span>' .
                                            '</div>'
                                        );
                                    }),
                            ])
                            ->hidden(fn (?BorrowRequest $record) => $record === null),
                    ])
                    ->columnSpan(['lg' => 1]),

                Group::make()
                    ->schema([
                        Section::make('List Unit')
                            ->schema([
                                Repeater::make('units')
                                    ->relationship()
                                    ->defaultItems(1)
                                    ->hiddenLabel()
                                    ->required()
                                    ->cloneable()
                                    ->collapsible()
                                    ->table([
                                        TableColumn::make('Unit'),
                                        TableColumn::make('Seal')
                                            ->width('200px'),
                                        TableColumn::make('Qty')
                                            ->width('80px'),
                                        TableColumn::make('Claim')
                                            ->width('80px'),
                                        TableColumn::make('Condition'),
                                    ])
                                    ->compact()
                                    ->schema([
                                        Select::make('unit_id')
                                            ->label('Unit')
                                            ->relationship('unit', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Unit $record) => $record->discontinued_at ? "{$record->name} (Dis:" . \Carbon\Carbon::parse($record->discontinued_at)->format('d/m/Y') . ")" : $record->name)
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if (! $state) return;

                                                $children = \App\Models\Unit::where('parent_id', $state)->get();
                                                if ($children->isEmpty()) return;

                                                $allItems = $get('../../units') ?? [];
                                                $existingUnitIds = collect($allItems)->pluck('unit_id')->filter()->all();
                                                $currentSeal = $get('seal');

                                                foreach ($children as $child) {
                                                    if (in_array($child->id, $existingUnitIds)) continue;

                                                    $allItems[] = [
                                                        'unit_id' => $child->id,
                                                        'seal' => $currentSeal,
                                                        'qty' => 1,
                                                        'damage' => null,
                                                        'is_claim' => false,
                                                    ];
                                                }

                                                $set('../../units', $allItems);
                                            }),
                                        DatePicker::make('seal')
                                            ->displayFormat('d/m/Y')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $unitId = $get('unit_id');
                                                if (! $unitId) return;

                                                $childIds = \App\Models\Unit::where('parent_id', $unitId)->pluck('id')->all();
                                                if (empty($childIds)) return;

                                                $allItems = $get('../../units') ?? [];
                                                $updated = false;
                                                foreach ($allItems as $key => $item) {
                                                    if (in_array($item['unit_id'] ?? null, $childIds)) {
                                                        $allItems[$key]['seal'] = $state;
                                                        $updated = true;
                                                    }
                                                }
                                                if ($updated) {
                                                    $set('../../units', $allItems);
                                                }
                                            }),
                                        TextInput::make('qty')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $unitId = $get('unit_id');
                                                if (! $unitId) return;

                                                $childIds = \App\Models\Unit::where('parent_id', $unitId)->pluck('id')->all();
                                                if (empty($childIds)) return;

                                                $allItems = $get('../../units') ?? [];
                                                $updated = false;
                                                foreach ($allItems as $key => $item) {
                                                    if (in_array($item['unit_id'] ?? null, $childIds)) {
                                                        $allItems[$key]['qty'] = $state;
                                                        $updated = true;
                                                    }
                                                }
                                                if ($updated) {
                                                    $set('../../units', $allItems);
                                                }
                                            }),
                                        Checkbox::make('is_claim')
                                            ->label('Claim'),
                                        TextInput::make('damage')
                                            ->label('Condition')
                                            ->required(function (Get $get) {
                                                $reqType = $get('../../request_type');
                                                $type = $reqType instanceof \App\Enums\BorrowRequestType ? $reqType->value : $reqType;
                                                if (request()->is('*create*') || str_contains(request()->header('referer', ''), 'create')) {
                                                    return $type === 'pull_request';
                                                }
                                                return true;
                                            })
                                            ->hidden(function (Get $get) {
                                                $reqType = $get('../../request_type');
                                                $type = $reqType instanceof \App\Enums\BorrowRequestType ? $reqType->value : $reqType;
                                                if (request()->is('*create*') || str_contains(request()->header('referer', ''), 'create')) {
                                                    return $type !== 'pull_request';
                                                }
                                                return false;
                                            }),
                                    ]),
                            ]),
                        Section::make('History')
                            ->schema([
                                RepeatableEntry::make('logs')
                                    ->hiddenLabel()
                                    ->table([
                                        TableColumn::make('Action At'),
                                        TableColumn::make('Author'),
                                        TableColumn::make('Action'),
                                        TableColumn::make('Date'),
                                        TableColumn::make('Note'),
                                        TableColumn::make('Details'),
                                    ])
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->dateTime('d M Y H:i:s'),
                                        TextEntry::make('actionAuthor.name'),
                                        TextEntry::make('action'),
                                        TextEntry::make('date')
                                            ->dateTime('d M Y'),
                                        TextEntry::make('note')
                                            ->limit(30, end: ' (more)')
                                            
                                            ->tooltip(function (TextEntry $component): ?\Illuminate\Support\HtmlString {
                                                $state = $component->getState();

                                                if (strlen($state) <= $component->getCharacterLimit()) {
                                                    return null;
                                                }

                                                // Wrap the content in a div with pre-wrap to preserve line breaks
                                                return new \Illuminate\Support\HtmlString(
                                                    '<div style="text-align: left; white-space: pre-wrap; min-width: 300px;">' . e($state) . '</div>'
                                                );
                                            }),
                                        TextEntry::make('details_formatted')
                                            ->label('Snapshot Barang')
                                            ->state(function ($record) {
                                                $details = $record->details;
                                                if (! is_array($details) || empty($details)) return '-';
                                                return collect($details)->map(function ($item) {
                                                    return ($item['name'] ?? 'Unknown') . ' (' . ($item['qty'] ?? 0) . ')';
                                                })->join(', ');
                                            })
                                    ])
                            ])
                            ->hidden(fn (?BorrowRequest $record) => $record === null),
                    ])->columnSpan(['lg' => 3]),

            ])->columns(4);
    }
}

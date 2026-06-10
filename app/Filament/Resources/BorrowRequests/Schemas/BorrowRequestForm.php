<?php

namespace App\Filament\Resources\BorrowRequests\Schemas;

use App\Models\BorrowRequest;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->relationship('warehouse', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('location_id')
                                    ->label('Location')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $location = Location::with('contactShippings.customer')->find($state);
                                        if ($location) {
                                            $set('shipping_address', $location->name);
                                            
                                            $contacts = $location->contactShippings->map(function ($cs) {
                                                return $cs->customer ? $cs->customer->name_tlp : null;
                                            })->filter()->join(', ');
                                            if ($contacts) {
                                                $set('shipping_contact', $contacts);
                                            }
                                        }
                                    }),
                                Textarea::make('shipping_address')
                                    ->label('Shipping Address')
                                    ->rows(2)
                                    ->required(),
                                TextInput::make('shipping_contact')
                                    ->label('Shipping Contact')
                                    ->required(),
                            ]),
                            
                        Section::make('Info')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('location.full_name')
                                    ->label('Location'),
                                TextEntry::make('rp_no')
                                    ->label('No. RP')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('requester.name')
                                    ->label('Requester'),
                                TextEntry::make('created_at')
                                    ->dateTime('d M Y H:i:s'),
                                TextEntry::make('note')
                                    ->label('Notes')
                                    ->limit(50)
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
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('pickup_address')
                                    ->label('Pickup Address')
                                    ->placeholder('Not set')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('pickup_contact')
                                    ->label('Pickup Contact')
                                    ->placeholder('Not set')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->copyMessageDuration(1500),
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
                                    ->table([
                                        TableColumn::make('Unit'),
                                        TableColumn::make('Seal')
                                            ->width('200px'),
                                        TableColumn::make('Qty')
                                            ->width('80px'),
                                        TableColumn::make('Condition'),
                                    ])
                                    ->schema([
                                        Select::make('unit_id')
                                            ->label('Unit')
                                            ->relationship('unit', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                        DatePicker::make('seal')
                                            ->displayFormat('d/m/Y')
                                            ->required(),
                                        TextInput::make('qty')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->required(),
                                        TextInput::make('damage')
                                            ->label('Condition')
                                            ->required(),
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
                                        TableColumn::make('Note'),
                                        TableColumn::make('Details'),
                                    ])
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->dateTime('d M Y H:i:s'),
                                        TextEntry::make('actionAuthor.name'),
                                        TextEntry::make('action'),
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

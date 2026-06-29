<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\LocationStatus;
use App\Models\Customer;
use App\Models\User;
use Fahiem\FilamentPinpoint\Pinpoint;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                Select::make('company_id')
                                    ->label('Company')
                                    ->relationship('company', 'alias')
                                    ->createOptionForm([
                                        TextInput::make('alias')
                                            ->unique(ignoreRecord: true)
                                            ->required(),
                                        TextInput::make('name')
                                            ->required(),
                                        TextInput::make('tlp')
                                            ->label('Phone')
                                            ->tel()
                                            ->required()
                                            ->maxLength(15)
                                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/'),
                                        TextInput::make('email')
                                            ->email()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(100)
                                            ->required(),
                                    ])
                                    ->createOptionAction(function (Action $action) {
                                        return $action
                                            ->modalHeading('Create company')
                                            ->modalSubmitActionLabel('Create company')
                                            ->modalWidth(Width::Large);
                                    }),
                                TextInput::make('name')
                                    ->required(),
                                Select::make('team_id')
                                    ->label('Team')
                                    ->relationship('team', 'name'),
                                Select::make('bd_id')
                                    ->label('Marketing')
                                    ->options(User::role('marketing')->pluck('name', 'id')),
                                Select::make('area_status')
                                    ->options([
                                        'in' => 'Dalam kota',
                                        'out' => 'Luar kota',
                                    ])
                                    ->required(),
                                Select::make('user_id')
                                    ->label('Pic')
                                    ->preload()
                                    ->searchable()
                                    ->options(User::role(['head_support', 'support'])->where('status', '!=', 0)->pluck('name', 'id')),
                                Select::make('status')
                                    ->options(LocationStatus::class)
                                    ->required(),
                                Toggle::make('is_ho')
                                    ->label('Is HO SAP?')
                                    ->default(false),
                                Textarea::make('address'),
                                Textarea::make('description'),
                                FileUpload::make('image')
                                    ->image()                                    
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Section::make()
                            ->schema([
                                Pinpoint::make('location')
                                    ->dehydrated(false)
                                    ->label('Map')
                                    ->latField('lat')
                                    ->lngField('lng')
                                    ->draggable()
                                    ->searchable()
                                    ->addressField('address')
                                    ->columnSpanFull(),
                                TextInput::make('google_coordinate')
                                    ->label('Google Maps Coordinate')
                                    ->placeholder('-6.177651165470643, 106.66342806640749')
                                    ->helperText('Paste koordinat dari Google Maps')
                                    ->live(onBlur: true)
                                    ->columnSpanFull()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function ($state, callable $set) {

                                        if (blank($state)) {
                                            return;
                                        }

                                        $parts = preg_split('/\s*,\s*/', trim($state));

                                        if (count($parts) !== 2) {
                                            return;
                                        }

                                        if (! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                                            return;
                                        }

                                        $set('lat', round((float) $parts[0], 7));
                                        $set('lng', round((float) $parts[1], 7));
                                    }),
                                TextInput::make('lat')
                                    ->label('Latitude')
                                    ->required()
                                    ->readOnly(),

                                TextInput::make('lng')
                                    ->label('Longitude')
                                    ->required()
                                    ->readOnly(),
                            ])
                            ->columns(2)
                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Contacts')
                            ->schema([
                                Placeholder::make('table_repeater_style')
                                    ->hiddenLabel()
                                    ->content(new \Illuminate\Support\HtmlString('
                                    <style>
                                        .force-table-repeater > table { display: table !important; width: 100% !important; }
                                        .force-table-repeater > table > thead { display: table-header-group !important; }
                                        .force-table-repeater > table > tbody { display: table-row-group !important; }
                                        .force-table-repeater > table > tbody > tr { display: table-row !important; border:none !important; }
                                        .force-table-repeater > table > tbody > tr > td { display: table-cell !important; padding: 0.5rem 0.75rem !important; vertical-align: middle; }
                                        .force-table-repeater .fi-fo-field-label-content { display: none !important; }
                                        .force-table-repeater .fi-in-entry-label { display: none !important; }
                                        .force-table-repeater > table > tbody > tr > td.fi-hidden { display: none !important; }
                                        .force-table-repeater > table > tbody > tr > td:last-child { width: 1% !important; padding: 0 0.5rem !important; white-space: nowrap; }
                                        .force-table-repeater > table > thead > tr > th:last-child { width: 1% !important; padding: 0 !important; }
                                        .force-table-repeater > table > tbody > tr > td > .fi-fo-table-repeater-actions { padding: 0 !important; width: auto !important; margin: 0 !important; justify-content: center; }
                                    </style>
                                ')),
                                Repeater::make('customerLocations')
                                    ->label('Notification Email')
                                    ->relationship()
                                    ->extraAttributes(['class' => 'force-table-repeater'])
                                    ->table([
                                        TableColumn::make('Email'),
                                        TableColumn::make('CC/To')
                                            ->width(75),
                                    ])
                                    ->compact()
                                    ->schema([
                                        \Filament\Forms\Components\Hidden::make('is_contact_shipping')->default(0),
                                        Select::make('customer_id')
                                            ->label('Email')
                                            ->relationship('customer', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (Customer $record) => $record->name_email)
                                            ->searchable(['name', 'email'])
                                            ->preload()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->required()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->required(),
                                                TextInput::make('tlp')
                                                    ->unique('customers', 'tlp', ignoreRecord: true)
                                                    ->tel()
                                                    ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                                                    ->required(),
                                                TextInput::make('email')
                                                    ->label('Email address')
                                                    ->email()
                                                    ->unique('customers', 'email', ignoreRecord: true)
                                                    ->required(),
                                                Textarea::make('description')
                                                    ->columnSpanFull(),
                                            ])
                                            ->createOptionAction(function (Action $action) {
                                                return $action
                                                    ->modalHeading('Create contact')
                                                    ->modalSubmitActionLabel('Create contact')
                                                    ->modalWidth(Width::Large);
                                            }),
                                        Toggle::make('is_to')
                                            ->label('CC/To'),
                                    ])
                                    ->addActionLabel('Add Email')
                                    ->defaultItems(1)
                                    ->collapsible(),
                                    
                                Repeater::make('contactShippings')
                                    ->label('Contact Shipping')
                                    ->relationship()
                                    ->extraAttributes(['class' => 'force-table-repeater'])
                                    ->table([
                                        TableColumn::make('name_tlp'),
                                    ])
                                    ->schema([
                                        \Filament\Forms\Components\Hidden::make('is_contact_shipping')->default(1),
                                        Select::make('customer_id')
                                            ->label('Contact')
                                            ->relationship('customer', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (Customer $record) => $record->name_tlp)
                                            ->searchable(['name', 'tlp'])
                                            ->preload()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->required()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->required(),
                                                TextInput::make('tlp')
                                                    ->tel()
                                                    ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                                                    ->unique('customers', 'tlp', ignoreRecord: true)
                                                    ->required(),
                                            ])
                                            ->createOptionAction(function (Action $action) {
                                                return $action
                                                    ->modalHeading('Create contact')
                                                    ->modalSubmitActionLabel('Create contact')
                                                    ->modalWidth(Width::Large);
                                            }),
                                    ])
                                    ->addActionLabel('Add PIC')
                                    ->defaultItems(1)
                                    ->maxItems(1)
                                    ->collapsible(),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}

<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\LocationStatus;
use App\Models\Customer;
use App\Models\User;
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
                                    ->options(User::role('support')->pluck('name', 'id')),
                                Select::make('status')
                                    ->options(LocationStatus::class)
                                    ->required(),
                                Textarea::make('address'),
                                Textarea::make('description'),
                                FileUpload::make('image')
                                    ->image()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Mail Client')
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
                                    ->label('Customer Location')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->extraAttributes(['class' => 'force-table-repeater'])
                                    ->table([
                                        TableColumn::make('Email'),
                                        TableColumn::make('To')
                                            ->width(75),
                                    ])
                                    ->schema([
                                        Select::make('customer_id')
                                            ->label('Email')
                                            ->options(Customer::all()->pluck('name_email', 'id'))
                                            ->searchable()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->required(),
                                        Toggle::make('is_to')
                                            ->label('CC/To'),
                                    ])
                                    ->addActionLabel('Add to Mail')
                                    ->defaultItems(1)
                                    ->collapsible(),
                            ])
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }
}

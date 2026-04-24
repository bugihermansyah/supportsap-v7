<?php

namespace App\Filament\Resources\Outstandings\Schemas;

use App\Enums\OutstandingTypeProblem;
use App\Models\Contract;
use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Team;
use App\Models\Unit;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OutstandingForm
{
    public static function configure(Schema $schema): Schema
    {
        if ($schema->getOperation() === 'edit') {
            return $schema->components(static::getEditSchema())->columns(5);
        }

        return $schema->components(static::getCreateSchema());
    }

    protected static function getCreateSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make()
                        ->schema([
                            Toggle::make('task')
                                ->label('Existing <-> New')
                                ->dehydrated(false)
                                ->live(),
                            Select::make('location_id')
                                ->label('Location')
                                ->searchable()
                                ->options(function () {
                                    $user = auth()->user();
                                    
                                    if ($user->hasAnyRole(['super_admin', 'admin', 'owner', 'head_support'])) {
                                        return Location::with('company')->get()->pluck('full_name', 'id');
                                    }

                                    return Location::where('team_id', $user->getTeamId())
                                        ->with('company')
                                        ->get()
                                        ->pluck('name_alias', 'id');
                                })
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if (!$state) return;

                                    $defaultProduct = Contract::query()
                                        ->where('location_id', $state)
                                        ->where('is_default', 1)
                                        ->join('products', 'products.id', '=', 'contracts.product_id')
                                        ->select('products.id')
                                        ->first();

                                    if ($defaultProduct) {
                                        $set('product_id', $defaultProduct->id);
                                    } else {
                                        $set('product_id', null);
                                    }
                                }),
                            Select::make('reporter')
                                ->label('Reporter')
                                ->visible(fn($get) => $get('task'))
                                ->options([
                                    'client' => 'Client',
                                    'preventif' => 'Preventif',
                                    'support' => 'Internal',
                                ])
                                ->default('client')
                                ->live()
                                ->required(),
                            TextInput::make('reporter_name')
                                ->label('Reporter Name')
                                ->visible(fn($get) => $get('task'))
                                ->required(),
                            DatePicker::make('date_in')
                                ->label('Info Date')
                                ->visible(fn($get) => $get('task'))
                                ->default(Carbon::now())
                                ->required()
                                ->native(true),
                            DatePicker::make('date_visit')
                                ->label('For Visit Date')
                                ->default(Carbon::now())
                                ->required()
                                ->native(true),
                            Select::make('user_id')
                                ->label('Support')
                                ->multiple()
                                ->searchable()
                                ->required()
                                ->options(function () {
                                    $teams = Team::with('users')->get();
                                    $options = [];

                                    foreach ($teams as $team) {
                                        $teamUsers = $team->users->pluck('name', 'id')->toArray();
                                        $options[$team->name] = $teamUsers;
                                    }

                                    return $options;
                                }),
                        ]),
                ]),
            Group::make()
                ->schema([
                    Section::make()
                        ->schema([
                            Repeater::make('problems')
                                ->label('Problem')
                                ->reorderable(false)
                                ->defaultItems(1)
                                ->simple(
                                    TextInput::make('title')
                                        ->hiddenLabel()
                                        ->required(),
                                )
                                ->visible(fn($get) => $get('task'))
                                ->defaultItems(1),
                            Fieldset::make('Produk')
                                ->schema([
                                    Radio::make('product_id')
                                        ->label('Produk')
                                        ->columnSpanFull()
                                        ->hiddenLabel()
                                        ->visible(fn($get) => $get('task'))
                                        ->options(fn(Get $get): Collection => Contract::query()
                                            ->where('location_id', $get('location_id'))
                                            ->join('products', 'products.id', '=', 'contracts.product_id')
                                            ->pluck('products.name', 'products.id'))
                                        ->required(),
                                ])
                                ->visible(fn($get) => $get('task'))
                                ->reactive(),
                            Fieldset::make('Status')
                                ->schema([
                                    Checkbox::make('is_implement')
                                        ->label('Implementasi')
                                        ->visible(fn($get) => $get('task'))
                                        ->inline(),
                                    Checkbox::make('is_oncall')
                                        ->label('OnCall')
                                        ->visible(fn($get) => $get('task'))
                                        ->inline(),
                                ])
                                ->visible(fn($get) => $get('task'))
                                ->reactive(),
                            CheckboxList::make('outstanding_id')
                                ->label('Problem')
                                ->columnSpanFull()
                                ->visible(fn($get) => !$get('task'))
                                ->options(function (Get $get): array {
                                    $options = Outstanding::query()
                                        ->where('location_id', $get('location_id'))
                                        ->where('outstandings.status', 0)
                                        ->whereNotExists(function ($query) {
                                            $query->select(DB::raw(1))
                                                ->from('reportings')
                                                ->whereColumn('reportings.outstanding_id', 'outstandings.id')
                                                ->whereNull('reportings.status');
                                        })
                                        ->pluck('title', 'id')
                                        ->toArray();

                                    return !empty($options) ? $options : ['' => 'No Outstanding'];
                                })
                                ->disableOptionWhen(fn(string $value): bool => $value === '')
                                ->required(),
                        ])
                ])
            ];
    }

    protected static function getEditSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make()
                        ->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('company_alias')
                                ->label('Company')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('location_id')
                                ->label('Location')
                                ->options(Location::query()->pluck('name', 'id'))
                                ->live()
                                ->afterStateHydrated(function ($set, $state) {
                                    if ($state) {
                                        $set('company_alias', \App\Models\Location::find($state)?->company?->alias);
                                    }
                                })
                                ->afterStateUpdated(function ($set, $state) {
                                    $set('company_alias', \App\Models\Location::find($state)?->company?->alias);
                                })
                                ->searchable()
                                ->required(),
                            Select::make('product_id')
                                ->label('Product')
                                ->options(fn (Get $get): Collection => Contract::query()
                                    ->where('location_id', $get('location_id'))
                                    ->join('products', 'products.id', '=', 'contracts.product_id')
                                    ->pluck('products.name', 'products.id'))
                                ->required(),
                            Select::make('reporter')
                                ->label('Reporter')
                                ->options([
                                    'client' => 'Client',
                                    'preventif' => 'Preventif',
                                    'support' => 'Support',
                                ])
                                ->default('client')
                                ->required(),
                            TextInput::make('reporter_name')
                                ->label('Reporter Name')
                                ->maxLength(100)
                                ->trim()
                                ->required(),
                            TextInput::make('title')
                                ->label('Problem')
                                ->maxLength(100)
                                ->trim()
                                ->required()
                                ->columnSpanFull(),
                            RichEditor::make('note')
                                ->columnSpan('full'),
                        ])
                        ->columns(2),
                    Section::make('Status')
                        ->schema([
                            Checkbox::make('status')
                                ->label('Closed'),
                            Checkbox::make('is_implement')
                                ->label('Implementasi'),
                            Checkbox::make('is_oncall')
                                ->label('Oncall'),
                        ])
                        ->columns(3),
                ])
                ->columnSpan(3),

            Group::make()
                ->schema([                    
                    Section::make('Problem Unit')
                        ->schema([
                            ToggleButtons::make('is_type_problem')
                                ->label('Type Problem')
                                ->options(OutstandingTypeProblem::class)
                                ->columnSpanFull()
                                ->required()
                                ->inline(),
                            \Filament\Forms\Components\Placeholder::make('table_repeater_style')
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
                            Repeater::make('outstandingUnits')
                                ->label('Unit')
                                ->relationship()
                                ->extraAttributes(['class' => 'force-table-repeater'])
                                ->table([
                                    TableColumn::make('Unit'),
                                    TableColumn::make('Qty')
                                        ->width(80),
                                ])
                                ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $livewire): array {
                                    $data['location_id'] = $livewire->getRecord()->location_id;

                                    return $data;
                                })
                                ->compact()
                                ->defaultItems(0)
                                ->hiddenLabel()
                                ->schema([
                                    Select::make('unit_id')
                                        ->label('Unit')
                                        ->options(Unit::query()->pluck('name', 'id'))
                                        ->searchable()
                                        ->required(),

                                    TextInput::make('qty')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(20)
                                        ->default(1)
                                        ->required(),
                                ]),
                        ]),

                    Section::make()
                        ->schema([
                            TextEntry::make('lpm')
                                ->label('Report Status')
                                ->state(fn (Outstanding $record): string => $record->lpm == 1 ? 'Laporan Awal Masuk' : '-'),
                            TextEntry::make('location.area_label')
                                ->label('Area'),
                            TextEntry::make('user.name')
                                ->label('Created by')
                                ->placeholder('-'),
                        ])
                        ->columns(2),
                ])
                ->columnSpan(2),
        ];
    }
}

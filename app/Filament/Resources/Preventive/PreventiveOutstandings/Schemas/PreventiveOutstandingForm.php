<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Schemas;

use App\Models\Contract;
use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Team;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PreventiveOutstandingForm
{
    public static function configure(Schema $schema): Schema
    {
        if (auth()->user()?->hasRole('preventive')) {
            return $schema->components(static::getPreventiveSchema());
        }

        return $schema->components(static::getHeadPreventiveSchema());
    }

    protected static function getHeadPreventiveSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make()
                        ->schema([
                            Select::make('location_id')
                                ->label('Location')
                                ->searchable()
                                ->options(Location::query()->pluck('name', 'id'))
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
                                ->options([
                                    'preventif' => 'Preventive',
                                ])
                                ->default('preventif')
                                ->required(),
                            Select::make('reporter_name')
                                ->label('Reporter Name')
                                ->options(\App\Models\User::role('preventive')->pluck('name', 'name'))
                                ->searchable()
                                ->required(),
                            DatePicker::make('date_in')
                                ->label('Info Date')
                                ->default(Carbon::now())
                                ->required()
                                ->native(true),
                            Fieldset::make('Produk')
                                ->schema([
                                    Radio::make('product_id')
                                        ->label('Produk')
                                        ->columnSpanFull()
                                        ->hiddenLabel()
                                        ->options(fn(Get $get): Collection => Contract::query()
                                            ->where('location_id', $get('location_id'))
                                            ->join('products', 'products.id', '=', 'contracts.product_id')
                                            ->pluck('products.name', 'products.id'))
                                        ->required(),
                                ])
                                ->reactive(),
                        ]),
                ]),
            Group::make()
                ->schema([
                    // Section::make()
                    //     ->schema([
                            Repeater::make('problems')
                                // ->label('Problema')
                                ->hiddenLabel()
                                ->reorderable(false)
                                ->defaultItems(1)
                                ->table([
                                    TableColumn::make('Problem'),
                                    // TableColumn::make('images')
                                ])
                                ->schema([
                                    TextInput::make('title')
                                        ->hiddenLabel()
                                        ->required(),
                                    // FileUpload::make('images')
                                    //     ->hiddenLabel()
                                    //     ->image()
                                    //     ->previewable(false)
                                    //     ->multiple()
                                ])
                                ->defaultItems(1),
                        ])
                // ])
            ];
    }

    protected static function getPreventiveSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make()
                        ->schema([
                            Select::make('location_id')
                                ->label('Location')
                                ->searchable()
                                ->options(Location::query()->pluck('name', 'id'))
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
                                ->options([
                                    'preventif' => 'Preventive',
                                ])
                                ->default('preventif')
                                ->required(),
                            TextInput::make('reporter_name')
                                ->label('Reporter Name')
                                ->default(fn() => auth()->user()?->name)
                                ->readOnly()
                                ->required(),
                            DatePicker::make('date_in')
                                ->label('Info Date')
                                ->default(Carbon::now())
                                ->required()
                                ->native(true),
                            Fieldset::make('Produk')
                                ->schema([
                                    Radio::make('product_id')
                                        ->label('Produk')
                                        ->columnSpanFull()
                                        ->hiddenLabel()
                                        ->options(fn(Get $get): Collection => Contract::query()
                                            ->where('location_id', $get('location_id'))
                                            ->join('products', 'products.id', '=', 'contracts.product_id')
                                            ->pluck('products.name', 'products.id'))
                                        ->required(),
                                ])
                                ->reactive(),
                        ]),
                ]),
            Group::make()
                ->schema([
                    // Section::make()
                    //     ->schema([
                            Repeater::make('problems')
                                // ->label('Problema')
                                ->hiddenLabel()
                                ->reorderable(false)
                                ->defaultItems(1)
                                ->table([
                                    TableColumn::make('Problem'),
                                    // TableColumn::make('images')
                                ])
                                ->schema([
                                    TextInput::make('title')
                                        ->hiddenLabel()
                                        ->required(),
                                    // FileUpload::make('images')
                                    //     ->hiddenLabel()
                                    //     ->image()
                                    //     ->previewable(false)
                                    //     ->multiple()
                                ])
                                ->defaultItems(1),
                        ])
                // ])
            ];
    }
}

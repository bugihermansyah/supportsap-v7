<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    TextInput::make('name')
                        ->required(),
                    Select::make('group')
                        ->options(['cass' => 'Cass', 'manless' => 'Manless', 'other' => 'Other'])
                        ->default('cass')
                        ->required(),
                    TextInput::make('point')
                        ->required()
                        ->numeric()
                        ->default(0),
                ])
            ]);
    }
}

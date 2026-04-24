<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('sort')
                    ->numeric(),
                TextInput::make('point')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('group')
                    ->options(['cass' => 'Cass', 'manless' => 'Manless', 'other' => 'Other'])
                    ->default('cass')
                    ->required(),
            ]);
    }
}

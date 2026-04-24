<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('company_id'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('team_id'),
                TextInput::make('bd_id'),
                TextInput::make('area_status')
                    ->required(),
                TextInput::make('user_id'),
                FileUpload::make('image')
                    ->image(),
                TextInput::make('type_contract'),
                TextInput::make('status')
                    ->required(),
                DatePicker::make('first_project'),
                TextInput::make('address'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

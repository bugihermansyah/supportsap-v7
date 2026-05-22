<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    FileUpload::make('image')
                        ->image(),
                    TextInput::make('name')
                        ->required(),
                ])
            ]);
    }
}

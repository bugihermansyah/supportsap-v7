<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('alias'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('tlp')
                    ->required()
                    ->default('0'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->default('0'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

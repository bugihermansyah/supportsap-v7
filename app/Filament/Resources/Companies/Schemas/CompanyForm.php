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
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

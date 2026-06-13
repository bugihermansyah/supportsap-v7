<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->dehydrated(false)
                    ->required(),
                ColorPicker::make('color'),
                TagsInput::make('email_to')
                    ->label('Email To')
                    ->placeholder('Tambahkan email utama')
                    ->nestedRecursiveRules([
                        'email',
                    ]),
                TagsInput::make('email_cc')
                    ->label('Email CC')
                    ->placeholder('Tambahkan email CC')
                    ->nestedRecursiveRules([
                        'email',
                    ]),
            ]);
    }
}

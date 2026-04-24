<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),

                TextInput::make('password')
                    ->password()
                    ->confirmed()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                TextInput::make('password_confirmation')
                    ->required(fn (string $context): bool => $context === 'create')
                    ->password()
                    ->dehydrated(false),

                Select::make('roles')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled()
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->default(fn () => auth()->check() && auth()->user()->hasRole('head_support') ? [\Spatie\Permission\Models\Role::where('name', 'support')->value('id')] : null)
                    ->label('Roles'),
                    
                Select::make('team_id')
                    ->relationship('team', 'name')
                    ->label('Team')
                    ->searchable()
                    ->disabled()
                    ->preload()
                    ->required()
                    ->default(fn () => auth()->check() && auth()->user()->hasRole('head_support') ? auth()->user()->team_id : null),
            ]);
    }
}

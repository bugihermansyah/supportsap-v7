<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                // Sembunyikan user dengan ID 'system'
                $query->where('id', '!=', 'system');

                $user = auth()->user();

                // Sembunyikan user super_admin bagi non-super_admin
                if ($user && !$user->hasRole('super_admin')) {
                    $query->whereDoesntHave('roles', fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('name', 'super_admin'));
                }

                // Filter berdasarkan team jika bukan role tertentu
                if ($user && !$user->hasRole('super_admin', 'manager_support', 'helpdesk') && $user->team_id) {
                    $query->where('team_id', $user->team_id);
                }
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('team.name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Impersonate::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}

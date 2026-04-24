<?php

namespace App\Filament\Resources\Outstandings\Tables;

use App\Enums\OutstandingStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OutstandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable(),
                TextColumn::make('location.company.alias')
                    ->label('Company')
                    ->searchable(),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->searchable()                    
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('reporter')
                    ->formatStateUsing(fn ($state) => ucwords($state))
                    ->searchable(),
                TextColumn::make('reporter_name')
                    ->searchable(),
                TextColumn::make('date_in')
                    ->label('Date Info')
                    ->date()
                    ->sortable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options(OutstandingStatus::class)
                    ->selectablePlaceholder(false),
                TextColumn::make('is_type_problem')
                    ->label('Type Problem')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Created By')
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
            ->striped()
            ->filters([
                //
            ])
            ->recordActions([
                // ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}

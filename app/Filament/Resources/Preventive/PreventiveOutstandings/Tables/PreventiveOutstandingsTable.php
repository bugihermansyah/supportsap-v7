<?php

namespace App\Filament\Resources\Preventive\PreventiveOutstandings\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PreventiveOutstandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('reporter', 'preventif'))
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
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Problem')
                    ->searchable(),
                TextColumn::make('reporter_name')
                    ->searchable(),
                TextColumn::make('date_in')
                    ->label('Date Info')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('last_reporting_status')
                    ->label('Last Action')
                    ->badge()
                    ->state(function ($record) {
                        $rawStatus = $record->reportings()->latest('created_at')->first()?->status;
                        return $rawStatus !== null ? \App\Enums\ReportStatus::tryFrom($rawStatus) : null;
                    }),
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
            ->defaultSort('date_in', 'desc')
            ->striped()
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}

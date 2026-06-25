<?php

namespace App\Filament\Resources\Locations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.alias')
                    ->label('Company')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable(),
                TextColumn::make('bd.name')
                    ->label('Marketing')
                    ->formatStateUsing(fn($state) => ucwords($state))
                    ->searchable(),
                // TextColumn::make('contracts.product.name')
                //     ->label('Product')
                //     ->listWithLineBreaks()
                //     ->badge()
                //     ->searchable(),
                // TextColumn::make('contracts.type_contract')
                //     ->label('Type')
                //     ->listWithLineBreaks()
                //     ->badge()
                //     ->searchable(),
                // TextColumn::make('contracts.bap')
                //     ->label('Status')
                //     ->listWithLineBreaks()
                //     ->badge()
                //     ->searchable(),
                TextColumn::make('contracts_combined')
                    ->label('Contracts Detail')
                    ->getStateUsing(function ($record) {
                        return $record->contracts->map(function ($contract) {
                            $productName = $contract->product ? $contract->product->name : '-';
                            return "{$productName} | {$contract->type_contract} | {$contract->bap}";
                        });
                    })
                    ->listWithLineBreaks()
                    ->badge()
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->whereHas('contracts', function ($q) use ($search) {
                            $q->where('type_contract', 'like', "%{$search}%")
                              ->orWhere('bap', 'like', "%{$search}%")
                              ->orWhereHas('product', function ($q2) use ($search) {
                                  $q2->where('name', 'like', "%{$search}%");
                              });
                        });
                    }),
                TextColumn::make('area_status')
                    ->label('Area')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Support')
                    ->formatStateUsing(fn($state) => ucwords($state))
                    ->searchable(),
                IconColumn::make('lat')
                    ->label('Map')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedMapPin),
                TextColumn::make('status')
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
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}

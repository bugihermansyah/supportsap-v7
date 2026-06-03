<?php

namespace App\Filament\Resources\Support\SupportReportings\Tables;

use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportReportingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('users', fn (Builder $q) => $q->where('user_id', auth()->id())))
            ->columns([
                TextColumn::make('outstanding.location.name')
                    ->label('Location')
                    ->searchable(),
                TextColumn::make('outstanding.reporter')
                    ->label('Reporter')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => ucwords($state)),
                TextColumn::make('users.name')
                    ->label('Support')
                    ->badge()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),
                // ->listWithLineBreaks(),
                TextColumn::make('date_visit')
                    ->label('Date Visit')
                    ->date(),
                TextColumn::make('outstanding.title')
                    ->label('Problem')
                    ->searchable(),
                TextColumn::make('cause')
                    ->label('Cause')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->html(),
                TextColumn::make('status'),
            ])
            ->defaultSort('date_visit', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('sendMail')
                    ->label('Send Mail')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->url(fn ($record): string => SupportReportingResource::getUrl('send-email', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}

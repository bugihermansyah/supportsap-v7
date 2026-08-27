<?php

namespace App\Filament\Resources\Quiz\QuizSessions\Tables;

use App\Filament\Pages\QuizLeaderboard;
use App\Models\QuizSession;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class QuizSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'questions',
                'attempts',
                'attempts as finished_attempts_count' => fn ($q) => $q->whereNotNull('finished_at'),
            ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('starts_at')
                    ->label('Dibuka')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ditutup')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (QuizSession $record) => $record->statusBadge()['label'])
                    ->color(fn (QuizSession $record) => $record->statusBadge()['color']),
                TextColumn::make('questions_count')
                    ->label('Soal')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'gray' : 'danger'),
                TextColumn::make('finished_attempts_count')
                    ->label('Peserta selesai')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Dipublikasikan'),
            ])
            ->recordActions([
                Action::make('leaderboard')
                    ->label('Leaderboard')
                    ->icon('heroicon-m-trophy')
                    ->color('warning')
                    ->url(fn (QuizSession $record) => QuizLeaderboard::getUrl(['session' => $record->id])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

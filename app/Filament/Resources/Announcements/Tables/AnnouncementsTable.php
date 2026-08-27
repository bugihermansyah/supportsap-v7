<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Enums\AnnouncementLevel;
use App\Models\Announcement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('reads'))
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('level')
                    ->label('Tingkat')
                    ->badge()
                    ->sortable(),
                TextColumn::make('target_roles')
                    ->label('Target')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Semua role'),
                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Langsung')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tanpa batas')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Announcement $record) => match (true) {
                        ! $record->is_published => 'Draft',
                        $record->starts_at !== null && $record->starts_at->gt(now()) => 'Terjadwal',
                        $record->ends_at !== null && $record->ends_at->lt(now()) => 'Berakhir',
                        default => 'Tayang',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Tayang' => 'success',
                        'Terjadwal' => 'info',
                        'Berakhir' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reads_count')
                    ->label('Dibaca')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_pinned')
                    ->label('Disematkan')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('level')
                    ->label('Tingkat')
                    ->options(AnnouncementLevel::class),
                TernaryFilter::make('is_published')
                    ->label('Dipublikasikan'),
            ])
            ->recordActions([
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

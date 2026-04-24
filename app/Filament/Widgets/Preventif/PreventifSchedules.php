<?php

namespace App\Filament\Widgets\Preventif;

use App\Models\Reporting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PreventifSchedules extends TableWidget
{
    protected static ?string $heading = 'Jadwal Preventif';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Reporting::where('status', null))
            ->columns([
                Stack::make([
                    TextColumn::make('date_visit')
                        ->label('Jadwal')
                        ->icon('heroicon-m-calendar-days')
                        ->date('d M Y'),
                    TextColumn::make('outstanding.location.full_name')
                        ->label('Lokasi')
                        ->icon('heroicon-m-map-pin'),
                    TextColumn::make('outstanding.title')
                        ->label('Masalah')
                        ->icon('heroicon-m-wrench-screwdriver'),
                ]),
            ])
            ->defaultSort('date_visit', direction: 'asc')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('openMap')
                    ->label('')
                    ->icon('heroicon-m-map-pin')
                    ->button()
                    ->disabled(fn($record) => empty($record->outstanding?->location?->latitude) || empty($record->outstanding?->location?->longitude))
                    ->tooltip('Lihat di Google Maps')
                    ->color('success')
                    ->url(function ($record) {
                        $location = $record->outstanding?->location;

                        if (
                            empty($location?->latitude) ||
                            empty($location?->longitude)
                        ) {
                            return null;
                        }

                        return "https://www.google.com/maps/search/?api=1&query={$location->latitude},{$location->longitude}";
                    })
                    ->openUrlInNewTab(),
                Action::make('start')
                    ->label('Start')
                    ->button()
                    ->action(function ($record) {
                        $record->update([
                            'start_work' => now(),
                        ]);
                    })
                    ->icon('heroicon-m-play-circle')
                    ->color('danger')
                    ->visible(fn(Model $record) => !$record->start_work)
                    ->requiresConfirmation()
                    ->modalHeading('Start work')
                    ->modalDescription('Yakin anda akan memulai tugas preventif ini?')
                    ->modalSubmitActionLabel('Yes, starting'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('preventif');
    }
}

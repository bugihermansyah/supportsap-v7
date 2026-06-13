<?php

namespace App\Filament\Widgets\Support;

use App\Models\Reporting;
use Filament\Actions\Action;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Schedules extends TableWidget
{
    protected static ?string $heading = 'Schedules';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Reporting::where('status', null)
                ->whereHas('users', fn (Builder $q) => $q->where('user_id', auth()->id()))
            )
            ->columns([
                Stack::make([
                    TextColumn::make('date_visit')
                        ->label('Schedule')
                        ->icon('heroicon-m-calendar-days')
                        ->date('d M Y'),
                    TextColumn::make('outstanding.location.full_name')
                        ->label('Location')
                        ->icon('heroicon-m-map-pin'),
                    TextColumn::make('outstanding.title')
                        ->label('Problem')
                        ->icon('heroicon-m-briefcase')
                ]),
            ])
            ->defaultSort('created_at', direction: 'desc')
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
                    ->tooltip('View on Google Maps')
                    ->color('success')
                    ->url(function ($record) {
                        $location = $record->outstanding?->location;

                        // Jika latitude atau longitude kosong/null, jangan buat URL
                        if (
                            empty($location?->latitude) ||
                            empty($location?->longitude)
                        ) {
                            return null; // atau '#' jika ingin tetap jadi link mati
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
                    ->modalDescription('Are you sure you want to start this Outstanding?')
                    ->modalSubmitActionLabel('Yes, starting'),
                Action::make('updateReport')
                    ->label('Report')
                    ->icon('heroicon-m-document-plus')
                    ->button()
                    ->color('success')
                    ->visible(fn(Model $record) => $record->start_work)
                    ->url(function ($record) {
                        return route('filament.admin.resources.support.support-reportings.edit', $record);
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function canView(): bool
    {
        // Bypass pengecekan saat Livewire melakukan update (menghindari error 403 saat klik Start)
        if (request()->routeIs('livewire.update')) {
            return true;
        }

        // Selalu tampilkan jika sedang berada di halaman ScheduleDashboard
        if (request()->routeIs('filament.admin.pages.schedule-dashboard')) {
            return true;
        }

        // Di dashboard utama (atau halaman lain), sembunyikan untuk role tertentu
        return !auth()->user()?->hasRole(['head_support', 'admin']);
    }
}

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
    protected static ?string $heading = 'Pastikan report sesuai urutan kunjungan!';

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
                        ->size('sm')
                        ->date('d M Y'),
                    TextColumn::make('outstanding.location.full_name')
                        ->label('Location')
                        ->size('sm')
                        ->icon('heroicon-m-map-pin'),
                    TextColumn::make('outstanding.title')
                        ->label('Problem')
                        ->size('sm')
                        ->icon('heroicon-m-briefcase')
                ])->space(1),
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
                    ->size('sm')
                    ->disabled(fn($record) => empty($record->outstanding?->location?->lat) || empty($record->outstanding?->location?->lng))
                    ->tooltip('View on Google Maps')
                    ->color('success')
                    ->url(function ($record) {
                        $location = $record->outstanding?->location;

                        if (
                            empty($location?->lat) ||
                            empty($location?->lng)
                        ) {
                            return null; 
                        }

                        return "https://www.google.com/maps/search/?api=1&query={$location->lat},{$location->lng}";
                    })
                    ->openUrlInNewTab(),
                Action::make('start')
                    ->label('Start')
                    ->button()
                    ->size('sm')
                    ->mountUsing(function ($record, $action) {
                        $userId = auth()->id();

                        // 1. Validasi jadwal yang lebih lama
                        $hasOlder = \App\Models\Reporting::whereHas('users', fn($q) => $q->where('user_id', $userId))
                            ->whereNull('status')
                            ->where('date_visit', '<', $record->date_visit)
                            ->exists();

                        if ($hasOlder) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Peringatan')
                                ->body('Mohon proses jadwal kunjungan terlama terlebih dahulu!')
                                ->send();
                            
                            $action->halt();
                        }

                        // 2. Validasi jadwal aktif di lokasi/tanggal berbeda
                        $activeSchedule = \App\Models\Reporting::whereHas('users', fn($q) => $q->where('user_id', $userId))
                            ->whereNull('status')
                            ->whereNotNull('start_work')
                            ->where('id', '!=', $record->id)
                            ->with('outstanding')
                            ->first();

                        if ($activeSchedule) {
                            $activeLocationId = $activeSchedule->outstanding?->location_id;
                            $activeDate = $activeSchedule->date_visit;

                            $currentLocationId = $record->outstanding?->location_id;
                            $currentDate = $record->date_visit;

                            if ($activeLocationId !== $currentLocationId || $activeDate !== $currentDate) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Peringatan')
                                    ->body('Selesaikan jadwal aktif di lokasi lain dahulu!')
                                    ->send();
                                    
                                $action->halt();
                            }
                        }
                    })
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
                Action::make('cancelStart')
                    ->label('')
                    ->icon('heroicon-m-x-circle')
                    ->button()
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn(Model $record) => $record->start_work)
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Pekerjaan')
                    ->modalDescription('Apakah Anda yakin ingin membatalkan pekerjaan (Start Work) untuk jadwal ini?')
                    ->modalSubmitActionLabel('Ya, batalkan')
                    ->action(function ($record) {
                        $record->update([
                            'start_work' => null,
                        ]);
                    }),
                Action::make('updateReport')
                    ->label('Report')
                    ->icon('heroicon-m-document-plus')
                    ->button()
                    ->size('sm')
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
        // 1. Hindari error 403 saat eksekusi Action Livewire (klik tombol Start/Livewire update)
        if (request()->routeIs('livewire.update') || request()->header('X-Livewire')) {
            return true;
        }

        // 2. Jika sedang berada di halaman Schedule Dashboard, selalu izinkan tampil
        if (request()->routeIs('filament.admin.pages.schedule-dashboard')) {
            return true;
        }

        // 3. Sembunyikan widget ini untuk head_support di tempat lain (seperti Dashboard utama)
        if (auth()->user()?->hasAnyRole(['head_support', 'head_preventive', 'admin', 'helpdesk', 'manager', 'owner', 'preventive'])) {
            return false;
        }

        return true;
    }
}

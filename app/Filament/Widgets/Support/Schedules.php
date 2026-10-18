<?php

namespace App\Filament\Widgets\Support;

use App\Enums\OutstandingTypeProblem;
use App\Enums\ReportingState;
use App\Filament\Resources\Outstandings\Schemas\OutstandingForm;
use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Schedules extends TableWidget
{
    protected static ?string $heading = 'Pastikan report sesuai urutan kunjungan!';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            // openSchedule = scheduled + in_progress; jadwal yang dibatalkan tidak ikut.
            ->query(fn (): Builder => Reporting::query()
                ->openSchedule()
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
                        ->icon('heroicon-m-briefcase'),
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
                $this->openTicketAction(),
            ])
            ->recordActions([
                Action::make('openMap')
                    ->label('')
                    ->icon('heroicon-m-map-pin')
                    ->button()
                    ->size('sm')
                    ->disabled(fn ($record) => empty($record->outstanding?->location?->lat) || empty($record->outstanding?->location?->lng))
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
                        $hasOlder = Reporting::query()
                            ->openSchedule()
                            ->whereHas('users', fn ($q) => $q->where('user_id', $userId))
                            ->where('date_visit', '<', $record->date_visit)
                            ->exists();

                        if ($hasOlder) {
                            Notification::make()
                                ->danger()
                                ->title('Peringatan')
                                ->body('Mohon proses jadwal kunjungan terlama terlebih dahulu!')
                                ->send();

                            $action->halt();
                        }

                        // 2. Validasi jadwal aktif di lokasi/tanggal berbeda
                        $activeSchedule = Reporting::query()
                            ->where('state', ReportingState::InProgress)
                            ->whereHas('users', fn ($q) => $q->where('user_id', $userId))
                            ->where('id', '!=', $record->id)
                            ->with('outstanding')
                            ->first();

                        if ($activeSchedule) {
                            $activeLocationId = $activeSchedule->outstanding?->location_id;
                            $activeDate = $activeSchedule->date_visit;

                            $currentLocationId = $record->outstanding?->location_id;
                            $currentDate = $record->date_visit;

                            if ($activeLocationId !== $currentLocationId || $activeDate !== $currentDate) {
                                Notification::make()
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
                    ->visible(fn (Model $record) => ! $record->start_work)
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
                    ->visible(fn (Model $record) => $record->start_work)
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Pekerjaan')
                    ->modalDescription('Apakah Anda yakin ingin membatalkan pekerjaan (Start Work) untuk jadwal ini?')
                    ->modalSubmitActionLabel('Ya, batalkan')
                    ->action(function ($record) {
                        $record->update([
                            'start_work' => null,
                        ]);
                    }),
                // Jadwal yang tidak jadi dikunjungi: ditutup dengan alasan, bukan
                // dibiarkan menggantung di daftar atau dihapus.
                Action::make('cancelSchedule')
                    ->label('')
                    ->icon('heroicon-m-no-symbol')
                    ->button()
                    ->color('danger')
                    ->size('sm')
                    ->tooltip('Batalkan jadwal')
                    ->visible(fn (Reporting $record) => $record->state === ReportingState::Scheduled)
                    ->schema([
                        Textarea::make('cancel_reason')
                            ->label('Alasan pembatalan')
                            ->required()
                            ->maxLength(255)
                            ->rows(2),
                    ])
                    ->modalHeading('Batalkan jadwal kunjungan')
                    ->modalDescription('Jadwal akan keluar dari daftar kerja kamu, tapi riwayatnya tetap tersimpan.')
                    ->modalSubmitActionLabel('Ya, batalkan')
                    ->modalCancelActionLabel('Kembali')
                    ->action(function (Reporting $record, array $data): void {
                        $record->cancel($data['cancel_reason']);

                        Notification::make()
                            ->success()
                            ->title('Jadwal dibatalkan')
                            ->send();
                    }),
                Action::make('updateReport')
                    ->label('Report')
                    ->icon('heroicon-m-document-plus')
                    ->button()
                    ->size('sm')
                    ->color('success')
                    ->visible(fn (Model $record) => $record->start_work)
                    ->url(function ($record) {
                        return route('filament.admin.resources.support.support-reportings.edit', $record);
                    }),
            ])
            ->toolbarActions([]);
    }

    /** Open Ticket uses the same create form and create-or-schedule modes as Outstanding. */
    protected function openTicketAction(): Action
    {
        return Action::make('createOutstanding')
            ->label('Open Ticket')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modalWidth(Width::ScreenExtraLarge)
            ->modalSubmitActionLabel('Create')
            ->schema(OutstandingForm::createSchema())
            ->action(fn (array $data) => $this->createTicket($data));
    }

    /** Apply the same new-outstanding and existing-outstanding scheduling flow as CreateOutstanding. */
    protected function createTicket(array $data): void
    {
        $location = Location::find($data['location_id'] ?? null);

        if (! $location) {
            Notification::make()
                ->title('Lokasi tidak valid')
                ->body('Pilih lokasi yang masih tersedia.')
                ->danger()
                ->send();

            return;
        }

        $isNewOutstanding = (bool) ($data['task'] ?? false);
        $requestedUserIds = collect((array) ($data['user_id'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $userIds = User::query()
            ->where('status', '!=', 0)
            ->whereIn('id', $requestedUserIds)
            ->pluck('id')
            ->all();
        $dateVisit = $data['date_visit'] ?? null;

        if (! $location) {
            Notification::make()
                ->title('Lokasi tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        if (! $requestedUserIds || count($userIds) !== count($requestedUserIds)) {
            Notification::make()
                ->title('Support tidak valid')
                ->body('Pilih minimal satu user Support aktif dari daftar.')
                ->danger()
                ->send();

            return;
        }

        if (blank($dateVisit)) {
            Notification::make()
                ->title('Tanggal jadwal wajib diisi')
                ->body('Isi Visit Date sebelum membuat jadwal.')
                ->danger()
                ->send();

            return;
        }

        $outstandingIds = collect((array) ($data['outstanding_id'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $problems = collect((array) ($data['problems'] ?? []))
            ->map(fn ($problem): array => [
                'title' => trim((string) (is_array($problem) ? ($problem['title'] ?? '') : $problem)),
                'level' => max(1, min(5, (int) (is_array($problem) ? ($problem['level'] ?? 3) : 3))),
            ])
            ->filter(fn (array $problem): bool => filled($problem['title']))
            ->values()
            ->all();

        if ($isNewOutstanding && ! $problems) {
            $problems = [['title' => 'Internal Activity', 'level' => 3]];
        }

        $existingOutstandings = collect();

        if (! $isNewOutstanding) {
            if (! $outstandingIds) {
                Notification::make()
                    ->title('Problem wajib dipilih')
                    ->body('Pilih minimal satu Outstanding untuk dijadwalkan.')
                    ->danger()
                    ->send();

                return;
            }

            $existingOutstandings = Outstanding::query()
                ->where('location_id', $location->id)
                ->where('status', 0)
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('reportings')
                        ->whereColumn('reportings.outstanding_id', 'outstandings.id')
                        ->whereNull('reportings.status');
                })
                ->whereIn('id', $outstandingIds)
                ->get();

            if ($existingOutstandings->count() !== count($outstandingIds)) {
                Notification::make()
                    ->title('Problem tidak valid')
                    ->body('Pilih Outstanding open yang belum memiliki jadwal aktif untuk lokasi tersebut.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $isHo = (bool) $location->is_ho;
        $reporter = $isHo ? 'support' : ($data['reporter'] ?? 'client');
        $reporterName = $isHo ? auth()->user()->name : ($data['reporter_name'] ?? '');
        $dateIn = $data['date_in'] ?? null;
        $lpmReporter = $data['reporter'] ?? 'client';
        $reportings = DB::transaction(function () use ($data, $location, $isHo, $reporter, $reporterName, $dateIn, $dateVisit, $userIds, $isNewOutstanding, $problems, $existingOutstandings, $lpmReporter): array {
            $targets = $existingOutstandings;

            if ($isNewOutstanding) {
                $targets = collect();

                foreach ($problems as $problem) {
                    $lpm = 0;

                    if ($lpmReporter === 'client') {
                        $dateInForLpm = $dateIn ?: today()->toDateString();
                        $lpm = Outstanding::query()
                            ->where('location_id', $location->id)
                            ->whereDate('date_in', $dateInForLpm)
                            ->where('lpm', 1)
                            ->exists() ? 0 : 1;
                    }

                    $outstanding = Outstanding::create([
                        'number' => 'SP-'.now()->format('ym').random_int(100000, 999999),
                        'user_id' => auth()->id(),
                        'location_id' => $location->id,
                        'team_id' => $location->team_id,
                        'product_id' => $isHo ? null : ($data['product_id'] ?? null),
                        'reporter' => $reporter,
                        'reporter_name' => $reporterName,
                        'title' => $problem['title'],
                        'level' => $problem['level'],
                        'is_implement' => $data['is_implement'] ?? false,
                        'is_oncall' => $data['is_oncall'] ?? false,
                        'is_type_problem' => OutstandingTypeProblem::NON->value,
                        'date_in' => $dateIn,
                        'date_visit' => $dateVisit,
                        'status' => '0',
                        'lpm' => $lpm,
                    ]);

                    $targets->push($outstanding);
                }
            }

            $result = [];

            foreach ($targets as $outstanding) {
                $targetLocation = $outstanding->location ?? $location;
                $reporting = Reporting::create([
                    'outstanding_id' => $outstanding->id,
                    'date_visit' => $dateVisit,
                    'status' => null,
                    'email_to' => $targetLocation->customers()->wherePivot('is_to', true)->pluck('email')->toArray(),
                    'email_cc' => $targetLocation->customers()->wherePivot('is_to', false)->pluck('email')->toArray(),
                ]);
                $reporting->users()->attach($userIds);
                $result[] = $reporting;
            }

            return $result;
        });

        if (! $reportings) {
            Notification::make()
                ->title('Tidak ada jadwal yang dibuat')
                ->danger()
                ->send();

            return;
        }

        $assignedUsers = User::query()->whereIn('id', $userIds)->get();
        $supportEmails = $assignedUsers->pluck('email')->filter()->values()->all();

        foreach ($reportings as $reporting) {
            $outstanding = $reporting->outstanding;
            $targetLocation = $outstanding?->location ?? $location;

            if ($supportEmails && class_exists(\App\Jobs\ScheduleMailJob::class)) {
                \App\Jobs\ScheduleMailJob::dispatch(
                    $supportEmails,
                    $dateVisit,
                    $targetLocation->company->alias ?? 'SAP',
                    $targetLocation->name,
                    $outstanding?->title ?? '-',
                    $outstanding?->reporter ?? '-',
                    $outstanding?->reporter_name ?? '-'
                )->onQueue('scheduleEmails');
            }

            foreach ($assignedUsers as $assignedUser) {
                Notification::make()
                    ->title('Jadwal baru')
                    ->body("Jadwal baru di {$targetLocation->name} untuk problem: {$outstanding?->title} pada {$dateVisit}")
                    ->info()
                    ->actions([
                        Action::make('view')
                            ->label('Lihat Jadwal')
                            ->url(route('filament.admin.pages.schedule-dashboard'))
                            ->markAsRead()
                            ->button(),
                    ])
                    ->sendToDatabase($assignedUser);
            }
        }

        Notification::make()
            ->title($isNewOutstanding ? 'Ticket berhasil dibuat dan jadwal dibuat!' : 'Jadwal berhasil dibuat!')
            ->body(count($reportings).' problem dijadwalkan untuk '.$dateVisit.'.')
            ->success()
            ->send();
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

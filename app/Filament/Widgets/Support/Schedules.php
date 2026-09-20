<?php

namespace App\Filament\Widgets\Support;

use App\Enums\OutstandingTypeProblem;
use App\Enums\ReportingState;
use App\Models\Contract;
use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Schedules extends TableWidget
{
    protected static ?string $heading = 'Pastikan report sesuai urutan kunjungan!';

    protected static ?int $sort = 1;

    /** Cache is_ho per lokasi supaya closure form tidak query berulang kali. */
    protected array $hoLocationCache = [];

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

    /**
     * Support dapat membuat outstanding sendiri. PIC bersifat opsional:
     * tanpa PIC tiket menunggu assignment Head Support, dengan PIC tiket
     * sekaligus dibuatkan jadwal kunjungan.
     */
    protected function openTicketAction(): Action
    {
        return Action::make('createOutstanding')
            ->label('Open Ticket')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Simpan')
            ->schema([
                Select::make('location_id')
                    ->label('Location')
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        $user = auth()->user();

                        if ($user->canViewAllSupportTeams()) {
                            return Location::with('company')->get()->pluck('full_name', 'id');
                        }

                        return Location::where('team_id', $user->getTeamId())
                            ->orWhere('is_ho', true)
                            ->with('company')
                            ->get()
                            ->pluck('full_name', 'id');
                    })
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if (! $state) {
                            $set('product_id', null);

                            return;
                        }

                        $defaultProduct = Contract::query()
                            ->where('location_id', $state)
                            ->where('is_default', 1)
                            ->join('products', 'products.id', '=', 'contracts.product_id')
                            ->select('products.id')
                            ->first();

                        $set('product_id', $defaultProduct?->id);
                    })
                    ->columnSpanFull(),

                Select::make('product_id')
                    ->label('Produk')
                    ->options(fn (Get $get): Collection => Contract::query()
                        ->where('location_id', $get('location_id'))
                        ->join('products', 'products.id', '=', 'contracts.product_id')
                        ->pluck('products.name', 'products.id'))
                    ->required(fn (Get $get) => ! $this->isHoLocation($get('location_id')))
                    ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id'))),

                Select::make('reporter')
                    ->label('Reporter')
                    ->options([
                        'client' => 'Client',
                        'support' => 'Internal',
                    ])
                    ->default('client')
                    ->required(fn (Get $get) => ! $this->isHoLocation($get('location_id')))
                    ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id'))),

                TextInput::make('reporter_name')
                    ->label('Reporter Name')
                    ->maxLength(100)
                    ->required(fn (Get $get) => ! $this->isHoLocation($get('location_id')))
                    ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id'))),

                Repeater::make('problems')
                    ->label('Problem')
                    ->reorderable(false)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->compact()
                    ->table([
                        TableColumn::make('Title'),
                    ])
                    ->schema([
                        TextInput::make('title')
                            ->hiddenLabel()
                            ->maxLength(100)
                            ->required(),
                        Hidden::make('level')
                            ->default(3),
                    ])
                    ->columnSpanFull(),

                Select::make('user_id')
                    ->label('PIC Support (Opsional)')
                    ->multiple()
                    ->searchable()
                    ->live()
                    ->helperText('Kosongkan jika tiket cukup masuk antrian. Pilih PIC untuk langsung membuat jadwal.')
                    ->options(function (): array {
                        return User::role(['support', 'head_support', 'support_ho'])
                            ->where('status', '!=', 0)
                            ->with('team')
                            ->orderBy('name')
                            ->get()
                            ->groupBy(fn (User $user) => $user->team?->name ?? 'Tanpa Team')
                            ->map(fn (Collection $users) => $users->pluck('name', 'id')->all())
                            ->all();
                    })
                    ->columnSpanFull(),

                DatePicker::make('date_visit')
                    ->label('Tanggal Jadwal')
                    ->default(today())
                    ->native(true)
                    ->visible(fn (Get $get): bool => filled($get('user_id')))
                    ->required(fn (Get $get): bool => filled($get('user_id')))
                    ->helperText('Wajib diisi jika PIC Support dipilih.')
                    ->columnSpanFull(),
            ])
            ->action(fn (array $data) => $this->createTicket($data));
    }

    /** Buat satu outstanding untuk setiap problem, lalu jadwal bila PIC dipilih. */
    protected function createTicket(array $data): void
    {
        $location = Location::find($data['location_id'] ?? null);
        $problems = collect((array) ($data['problems'] ?? []))
            ->map(function ($problem): array {
                if (! is_array($problem)) {
                    return [
                        'title' => trim((string) $problem),
                        'level' => 3,
                    ];
                }

                return [
                    'title' => trim((string) ($problem['title'] ?? '')),
                    'level' => max(1, min(5, (int) ($problem['level'] ?? 3))),
                ];
            })
            ->filter(fn (array $problem): bool => filled($problem['title']))
            ->values()
            ->all();
        $requestedPicIds = collect((array) ($data['user_id'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $picIds = User::role(['support', 'head_support', 'support_ho'])
            ->where('status', '!=', 0)
            ->whereIn('id', $requestedPicIds)
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

        if (! $problems) {
            Notification::make()
                ->title('Problem wajib diisi')
                ->body('Tambahkan minimal satu problem sebelum menyimpan.')
                ->danger()
                ->send();

            return;
        }

        if (count($picIds) !== count($requestedPicIds)) {
            Notification::make()
                ->title('PIC Support tidak valid')
                ->body('Pilih PIC Support aktif yang tersedia pada daftar.')
                ->danger()
                ->send();

            return;
        }

        if ($picIds && blank($dateVisit)) {
            Notification::make()
                ->title('Tanggal jadwal wajib diisi')
                ->body('Isi tanggal jadwal jika PIC Support dipilih.')
                ->danger()
                ->send();

            return;
        }

        $isHo = (bool) $location->is_ho;
        $today = now()->toDateString();
        $reporter = $isHo ? 'support' : ($data['reporter'] ?? 'client');
        $reporterName = $isHo ? auth()->user()->name : ($data['reporter_name'] ?? '');
        $outstandings = [];
        $reportings = [];

        DB::transaction(function () use ($data, $location, $isHo, $today, $reporter, $reporterName, $problems, $picIds, $dateVisit, &$outstandings, &$reportings): void {
            foreach ($problems as $problem) {
                $lpm = 0;

                if (! $isHo && $reporter === 'client') {
                    $lpm = Outstanding::where('location_id', $location->id)
                        ->whereDate('date_in', $today)
                        ->where('lpm', 1)
                        ->exists() ? 0 : 1;
                }

                $outstanding = Outstanding::create([
                    'number' => 'SP-'.now()->format('ym').random_int(100000, 999999),
                    'user_id' => auth()->id(),
                    'location_id' => $location->id,
                    'team_id' => $location->team_id,
                    'product_id' => $data['product_id'] ?? null,
                    'reporter' => $reporter,
                    'reporter_name' => $reporterName,
                    'title' => $problem['title'],
                    'level' => $problem['level'],
                    'is_type_problem' => OutstandingTypeProblem::NON->value,
                    'date_in' => $today,
                    'date_visit' => $picIds ? $dateVisit : null,
                    'status' => '0',
                    'lpm' => $lpm,
                ]);
                $outstandings[] = $outstanding;

                if (! $picIds) {
                    continue;
                }

                $reporting = Reporting::create([
                    'outstanding_id' => $outstanding->id,
                    'date_visit' => $dateVisit,
                    'status' => null,
                    'email_to' => $location->customers()->wherePivot('is_to', true)->pluck('email')->toArray(),
                    'email_cc' => $location->customers()->wherePivot('is_to', false)->pluck('email')->toArray(),
                ]);

                $reporting->users()->attach($picIds);
                $reportings[] = $reporting;
            }
        });

        if (! $reportings) {
            Notification::make()
                ->title('Ticket berhasil dibuat!')
                ->body(count($outstandings).' outstanding menunggu Head Support untuk assign jadwal.')
                ->success()
                ->send();

            return;
        }

        $assignedUsers = User::whereIn('id', $picIds)->get();
        $supportEmails = $assignedUsers->pluck('email')->filter()->values()->all();

        foreach ($outstandings as $outstanding) {
            if ($supportEmails && class_exists(\App\Jobs\ScheduleMailJob::class)) {
                \App\Jobs\ScheduleMailJob::dispatch(
                    $supportEmails,
                    $dateVisit,
                    $location->company->alias ?? 'SAP',
                    $location->name,
                    $outstanding->title,
                    $outstanding->reporter ?? '-',
                    $outstanding->reporter_name ?? '-'
                )->onQueue('scheduleEmails');
            }

            foreach ($assignedUsers as $assignedUser) {
                Notification::make()
                    ->title('Jadwal baru')
                    ->body("Jadwal baru di {$location->name} untuk problem: {$outstanding->title} pada {$dateVisit}")
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
            ->title('Ticket berhasil dibuat dan jadwal dibuat!')
            ->body(count($outstandings).' outstanding dijadwalkan untuk '.$dateVisit.'.')
            ->success()
            ->send();
    }

    protected function isHoLocation(mixed $locationId): bool
    {
        if (blank($locationId)) {
            return false;
        }

        return $this->hoLocationCache[$locationId] ??= (bool) Location::whereKey($locationId)->value('is_ho');
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

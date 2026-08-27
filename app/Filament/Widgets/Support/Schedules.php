<?php

namespace App\Filament\Widgets\Support;

use App\Enums\OutstandingTypeProblem;
use App\Enums\PendingReason;
use App\Enums\ReportingState;
use App\Enums\ReportStatus;
use App\Jobs\CalculateSupportTravelDistance;
use App\Models\Contract;
use App\Models\Location;
use App\Models\Outstanding;
use App\Models\OutstandingUnit;
use App\Models\Reporting;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

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
     * Support membuka tiket sendiri (biasanya problem yang ditemukan di lapangan),
     * dengan dua pilihan alur:
     *   1. Lapor saja  -> tiket masuk antrian, jadwal diatur head_support.
     *   2. Lapor & langsung report -> tiket + report sekaligus, isian formnya
     *      mengikuti SupportReportingForm.
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
                Wizard::make([
                    Step::make('Ticket')
                        ->icon('heroicon-m-ticket')
                        ->description('Data problem')
                        ->columns(2)
                        ->schema([
                            ToggleButtons::make('direct_repair')
                                ->label('Alur laporan')
                                ->boolean('Lapor & Langsung Report', 'Lapor Saja')
                                ->icons([
                                    1 => 'heroicon-m-wrench-screwdriver',
                                    0 => 'heroicon-m-megaphone',
                                ])
                                ->colors([
                                    1 => 'success',
                                    0 => 'gray',
                                ])
                                ->default(false)
                                ->inline()
                                ->grouped()
                                ->live()
                                ->helperText(fn (Get $get): HtmlString => new HtmlString(
                                    $get('direct_repair')
                                        ? 'Anda menangani problem ini <strong>sekarang juga</strong> — report langsung tersimpan.'
                                        : 'Tiket masuk antrian, <strong>jadwal kunjungan diatur Head Support</strong>.'
                                ))
                                ->columnSpanFull(),

                            Select::make('location_id')
                                ->label('Location')
                                ->searchable()
                                ->preload()
                                ->options(function () {
                                    $user = auth()->user();

                                    if ($user->hasAnyRole(['super_admin', 'admin', 'owner', 'head_support'])) {
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

                            Select::make('level')
                                ->label('Tingkat Kesulitan')
                                ->options([
                                    1 => 'Very Easy',
                                    2 => 'Easy',
                                    3 => 'Normal',
                                    4 => 'Hard',
                                    5 => 'Very Hard',
                                ])
                                ->default(3)
                                ->required(),

                            Select::make('reporter')
                                ->label('Reporter')
                                ->options([
                                    'client' => 'Client',
                                    'support' => 'Internal',
                                ])
                                ->default('client')
                                ->required(fn (Get $get) => ! $this->isHoLocation($get('location_id')))
                                ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id')))
                                ->live(),

                            TextInput::make('reporter_name')
                                ->label('Reporter Name')
                                ->maxLength(100)
                                ->required(fn (Get $get) => ! $this->isHoLocation($get('location_id')))
                                ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id'))),

                            TextInput::make('title')
                                ->label('Problem (Title)')
                                ->maxLength(100)
                                ->required()
                                ->columnSpanFull(),
                        ]),

                    Step::make('Reporting')
                        ->icon('heroicon-m-document-text')
                        ->description('Pekerjaan yang dilakukan')
                        ->visible(fn (Get $get) => (bool) $get('direct_repair'))
                        ->columns(2)
                        ->schema([
                            DateTimePicker::make('start_work')
                                ->label('Start Work')
                                ->seconds(false)
                                ->default(now())
                                ->required(),
                            DateTimePicker::make('end_work')
                                ->label('End Work')
                                ->seconds(false)
                                ->default(now())
                                ->afterOrEqual('start_work')
                                ->required(),
                            ToggleButtons::make('work')
                                ->label('Action Type')
                                ->inline()
                                ->grouped()
                                ->options([
                                    'visit' => 'Visit',
                                    'remote' => 'Remote',
                                ])
                                ->colors([
                                    'visit' => 'info',
                                    'remote' => 'warning',
                                ])
                                ->default('visit')
                                ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id')))
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('cause')
                                ->label('Reason')
                                ->placeholder('Penyebab problem')
                                ->required()
                                ->columnSpanFull(),
                            RichEditor::make('action')
                                ->label('Action')
                                ->required()
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'orderedList',
                                ])
                                ->extraInputAttributes(['style' => 'min-height: 90px;'])
                                ->columnSpanFull(),
                            RichEditor::make('note')
                                ->label('Note')
                                ->hidden(fn (Get $get) => $this->isHoLocation($get('location_id')))
                                ->toolbarButtons([
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'orderedList',
                                ])
                                ->extraInputAttributes(['style' => 'min-height: 50px;'])
                                ->columnSpanFull(),
                        ]),

                    Step::make('Status & Unit')
                        ->icon('heroicon-m-check-badge')
                        ->description('Hasil pekerjaan')
                        ->visible(fn (Get $get) => (bool) $get('direct_repair') && ! $this->isHoLocation($get('location_id')))
                        ->columns(2)
                        ->schema([
                            ToggleButtons::make('status')
                                ->label('Status Report')
                                ->inline()
                                ->live()
                                ->options(ReportStatus::class)
                                ->helperText(new HtmlString('Jika selain <strong>Finish</strong> wajib isi next target'))
                                ->default(ReportStatus::Finish->value)
                                ->required()
                                ->columnSpanFull(),
                            // Pending SAP / Pending Client wajib menyertakan alasannya.
                            Select::make('pending_reason')
                                ->label('Alasan pending')
                                ->options(PendingReason::class)
                                ->native(false)
                                ->visible(fn (Get $get) => PendingReason::isRequiredFor($get('status')))
                                ->required(fn (Get $get) => PendingReason::isRequiredFor($get('status')))
                                ->columnSpanFull(),
                            DatePicker::make('revisit')
                                ->label('Revisit')
                                ->placeholder('Date next target')
                                ->requiredIf('status', ['0', '2', '3', '4'])
                                ->hidden(function (Get $get): bool {
                                    $status = $get('status');
                                    $status = $status instanceof ReportStatus ? $status->value : $status;

                                    return blank($status) || $status === ReportStatus::Finish->value;
                                })
                                ->native(true)
                                ->columnSpanFull(),
                            ToggleButtons::make('is_type_problem')
                                ->label('Problem Type')
                                ->helperText(new HtmlString('Setiap <strong>tipe problem</strong> wajib menyertakan kerusakan unit'))
                                ->options(OutstandingTypeProblem::class)
                                ->default(OutstandingTypeProblem::NON->value)
                                ->required()
                                ->inline()
                                ->columnSpanFull(),
                            Repeater::make('outstandingUnits')
                                ->label('Unit')
                                ->reorderable(false)
                                ->defaultItems(1)
                                ->minItems(1)
                                ->compact()
                                ->table([
                                    TableColumn::make('Name'),
                                    TableColumn::make('Qty')->width('90px'),
                                ])
                                ->schema([
                                    Select::make('unit_id')
                                        ->label('Unit')
                                        ->options(Unit::where('is_visible', 1)->pluck('name', 'id'))
                                        ->searchable()
                                        ->placeholder('Select unit')
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                    TextInput::make('qty')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->maxValue(20)
                                        ->minValue(1),
                                ])
                                ->columnSpanFull(),
                        ]),

                    Step::make('Attachments')
                        ->icon('heroicon-m-photo')
                        ->description('Foto & form support')
                        ->visible(fn (Get $get) => (bool) $get('direct_repair') && ! $this->isHoLocation($get('location_id')))
                        ->columns(2)
                        ->schema([
                            // FileUpload biasa (bukan SpatieMediaLibraryFileUpload): form action
                            // tidak punya record, jadi komponen media library tidak akan pernah
                            // menyimpan apa pun. File ditampung sementara di disk public lalu
                            // dipindah ke media collection setelah reporting dibuat.
                            FileUpload::make('attachments')
                                ->label('Photos')
                                ->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png'])
                                ->multiple()
                                ->maxFiles(10)
                                ->maxSize(10240)
                                ->disk('public')
                                ->directory('ticket-uploads/'.auth()->id())
                                ->optimize('jpg', 50)
                                ->resize(50)
                                ->maxImageWidth(1360)
                                ->imageEditor()
                                ->openable()
                                ->downloadable()
                                ->previewable(false)
                                ->preserveFilenames()
                                ->helperText('Maksimal 10 foto, masing-masing 10 MB.')
                                ->columnSpanFull(),
                            FileUpload::make('form_support')
                                ->label('Form Support')
                                ->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png'])
                                ->maxSize(10240)
                                ->disk('public')
                                ->directory('ticket-uploads/'.auth()->id())
                                ->optimize('jpg', 50)
                                ->resize(50)
                                ->openable()
                                ->downloadable()
                                ->previewable(true)
                                ->preserveFilenames()
                                ->helperText('Form support yang sudah ditandatangani client.')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpanFull(),
            ])
            ->action(fn (array $data) => $this->createTicket($data));
    }

    /**
     * Membuat outstanding (+ reporting bila langsung dikerjakan).
     *
     * Aturan data mengikuti CreateOutstanding dan EditSupportReporting supaya
     * tiket yang dibuka support identik dengan yang dibuat head_support.
     */
    protected function createTicket(array $data): void
    {
        $location = Location::find($data['location_id']);

        if (! $location) {
            Notification::make()
                ->title('Lokasi tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        $isHo = (bool) $location->is_ho;
        $directRepair = (bool) ($data['direct_repair'] ?? false);
        $today = now()->toDateString();

        // Lokasi HO selalu tercatat sebagai laporan internal atas nama user login.
        $reporter = $isHo ? 'support' : ($data['reporter'] ?? 'client');
        $reporterName = $isHo ? auth()->user()->name : ($data['reporter_name'] ?? '');

        // LPM (Laporan Awal Masuk) hanya untuk laporan pertama dari client pada
        // lokasi & tanggal yang sama — aturan sama dengan CreateOutstanding.
        $lpm = 0;
        if (! $isHo && $reporter === 'client') {
            $lpm = Outstanding::where('location_id', $location->id)
                ->whereDate('date_in', $today)
                ->where('lpm', 1)
                ->exists() ? 0 : 1;
        }

        // Report HO dipaksa Finish, sama seperti EditSupportReporting.
        $reportStatus = $isHo
            ? ReportStatus::Finish->value
            : (string) ($data['status'] ?? ReportStatus::Finish->value);

        $outstanding = null;
        $reporting = null;

        DB::transaction(function () use ($data, $location, $isHo, $directRepair, $today, $reporter, $reporterName, $lpm, $reportStatus, &$outstanding, &$reporting) {
            $outstanding = Outstanding::create([
                'number' => 'SP-'.now()->format('ym').random_int(100000, 999999),
                'user_id' => auth()->id(),
                'location_id' => $location->id,
                'team_id' => $location->team_id,
                'product_id' => $data['product_id'] ?? null,
                'reporter' => $reporter,
                'reporter_name' => $reporterName,
                'title' => $data['title'],
                'level' => $data['level'],
                'is_type_problem' => $data['is_type_problem'] ?? OutstandingTypeProblem::NON->value,
                'date_in' => $today,
                'date_visit' => $directRepair ? $today : null,
                'status' => '0',
                'lpm' => $lpm,
            ]);

            if (! $directRepair) {
                return;
            }

            $reporting = Reporting::create([
                'outstanding_id' => $outstanding->id,
                'date_visit' => $today,
                'status' => $reportStatus,
                // Dibersihkan otomatis oleh model kalau statusnya bukan pending.
                'pending_reason' => $data['pending_reason'] ?? null,
                'start_work' => $data['start_work'],
                'end_work' => $data['end_work'],
                'cause' => $data['cause'] ?? null,
                'action' => $data['action'],
                'note' => $data['note'] ?? null,
                'work' => $isHo ? 'visit' : ($data['work'] ?? 'visit'),
                'revisit' => $data['revisit'] ?? null,
                'email_to' => $location->customers()->wherePivot('is_to', true)->pluck('email')->toArray(),
                'email_cc' => $location->customers()->wherePivot('is_to', false)->pluck('email')->toArray(),
            ]);

            $reporting->users()->attach(auth()->id());

            foreach ($data['outstandingUnits'] ?? [] as $unit) {
                OutstandingUnit::create([
                    'outstanding_id' => $outstanding->id,
                    'location_id' => $location->id,
                    'unit_id' => $unit['unit_id'],
                    'qty' => $unit['qty'],
                ]);
            }

            // Status outstanding mengikuti pemetaan EditSupportReporting::afterSave().
            $outstanding->update(match ($reportStatus) {
                '1' => ['status' => '1', 'date_finish' => $today],
                '3' => ['status' => '0', 'date_finish' => $today, 'date_temporary' => $today],
                '2', '4' => ['status' => '0', 'date_finish' => $today],
                default => ['status' => '0', 'date_finish' => null],
            });
        });

        if (! $directRepair || ! $reporting) {
            Notification::make()
                ->title('Ticket berhasil dibuat!')
                ->body('Menunggu Head Support untuk assign jadwal.')
                ->success()
                ->send();

            return;
        }

        $this->attachMedia($reporting, $data);

        CalculateSupportTravelDistance::dispatch($reporting->id);

        Notification::make()
            ->title('Ticket berhasil dibuat dan report langsung disimpan!')
            ->success()
            ->send();
    }

    /**
     * Pindahkan file dari disk sementara ke media collection, lalu hitung ulang
     * score: calculateAutoScore() berjalan saat creating, ketika media belum ada,
     * sehingga tanpa perhitungan ulang report selalu kena penalti tidak berfoto.
     */
    protected function attachMedia(Reporting $reporting, array $data): void
    {
        foreach ((array) ($data['attachments'] ?? []) as $path) {
            if (blank($path)) {
                continue;
            }

            $reporting->addMediaFromDisk($path, 'public')->toMediaCollection('attachments');
        }

        $formSupport = $data['form_support'] ?? null;
        $formSupport = is_array($formSupport) ? reset($formSupport) : $formSupport;

        if (filled($formSupport)) {
            $reporting->addMediaFromDisk($formSupport, 'public')->toMediaCollection('form_support');
        }

        $reporting->load('media');
        $reporting->score = $reporting->calculateAutoScore();
        $reporting->saveQuietly();
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

<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

use Alsaloul\ImageGallery\Infolists\Entries\ImageGalleryEntry;
use App\Enums\PendingReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use App\Jobs\CalculateSupportTravelDistance;
use App\Mail\SupportReportingMail;
use App\Models\Customer;
use App\Models\Reporting;
use App\Models\ReportingEmail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SendEmailReporting extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = SupportReportingResource::class;

    protected string $view = 'filament.pages.support-reportings.send-email';

    public ?array $data = [];

    public ?array $ticketDetailsData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load(['outstanding.location.team', 'outstanding.location.company', 'users']);

        // Fetch email_to and email_cc from the location's customers pivot
        $location = $this->record->outstanding?->location;

        $emailTo = $location ? $location->customers()
            ->wherePivot('is_to', true)
            ->pluck('email')
            ->toArray() : [];

        $emailCc = $location ? $location->customers()
            ->wherePivot('is_to', false)
            ->pluck('email')
            ->toArray() : [];

        $this->form->fill([
            'cause' => $this->record->cause,
            'action' => $this->record->action,
            'note' => $this->record->note,
            'email_to' => $emailTo,
            'email_cc' => $emailCc,
            'start_work' => $this->record->start_work,
            'end_work' => $this->record->end_work,
        ]);

        $this->ticketDetailsForm->fill([
            'title' => $this->record->outstanding?->title,
            'reporter' => $this->record->outstanding?->reporter,
            'reporter_name' => $this->record->outstanding?->reporter_name,
            'date_visit' => $this->record->date_visit,
            'work' => $this->record->work,
            'support_user_ids' => $this->record->users->modelKeys(),
            'status' => $this->record->status?->value,
            'pending_reason' => $this->record->pending_reason?->value,
            'revisit' => $this->record->revisit,
        ]);
    }

    public function getTitle(): string
    {
        return $this->record->location_title ?? 'Send Email';
    }

    public function getHeading(): string
    {
        return $this->record->location_title ?? 'Send Email';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->statePath('data')
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Email Recipients')
                            ->schema([
                                Select::make('email_to')
                                    ->label('Email To')
                                    ->multiple()
                                    ->required()
                                    ->options(Customer::all()->pluck('name_email', 'email')),
                                Select::make('email_cc')
                                    ->label('Email CC')
                                    ->multiple()
                                    ->options(Customer::all()->pluck('name_email', 'email')),
                                DateTimePicker::make('start_work')
                                    ->label('Start Work')
                                    ->required(),
                                DateTimePicker::make('end_work')
                                    ->label('End Work')
                                    ->required(),
                            ])
                            ->columns(2),
                        Section::make('Detail Aksi')
                            ->schema([
                                TextInput::make('cause')
                                    ->label('Sebab')
                                    ->required(),
                                RichEditor::make('action')
                                    ->label('Aksi')
                                    ->required()
                                    ->toolbarButtons([
                                        'bold',
                                        'bulletList',
                                        'italic',
                                        'orderedList',
                                    ]),
                                RichEditor::make('note')
                                    ->label('Keterangan')
                                    ->toolbarButtons([
                                        'bold',
                                        'bulletList',
                                        'italic',
                                        'orderedList',
                                    ]),
                            ]),

                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Attachment')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('attachments')
                                    ->hiddenLabel()
                                    ->image()
                                    ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png'])
                                    ->multiple()
                                    ->maxSize(10240)
                                    ->optimize('jpg', 75)
                                    ->resize(75)
                                    ->imageEditor()
                                    ->panelLayout('grid')
                                    ->openable()
                                    ->collection('attachments')
                                    ->downloadable()
                                    ->maxImageWidth(1360)
                                    // ->maxImageHeight(1080)
                                    ->maxFiles(10)
                                    ->preserveFilenames()
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Form Support')
                            ->schema([
                                ImageGalleryEntry::make('form_support')
                                    ->hiddenLabel()
                                    ->state(fn (): array => $this->getFormSupportUrls())
                                    ->thumbWidth(120)
                                    ->thumbHeight(120)
                                    ->visible(fn (): bool => filled($this->getFormSupportUrls()))
                                    ->columnSpanFull(),
                                TextEntry::make('form_support_empty')
                                    ->hiddenLabel()
                                    ->state('Belum ada foto form support.')
                                    ->color('gray')
                                    ->visible(fn (): bool => blank($this->getFormSupportUrls()))
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    protected function getForms(): array
    {
        return ['form', 'ticketDetailsForm'];
    }

    public function ticketDetailsForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->statePath('ticketDetailsData')
            ->components([
                Section::make('Detail Tiket')
                    ->schema([
                        TextEntry::make('outstanding.number')
                            ->label('No. Tiket'),
                        TextEntry::make('outstanding.location.name')
                            ->label('Lokasi'),
                        TextInput::make('title')
                            ->label('Masalah')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        Select::make('reporter')
                            ->label('Reporter')
                            ->options([
                                'client' => 'Client',
                                'preventif' => 'Preventif',
                                'support' => 'Internal',
                            ])
                            ->native(false)
                            ->in(['client', 'preventif', 'support'])
                            ->required(fn (): bool => ! $this->reporting()->outstanding?->location?->is_ho)
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        TextInput::make('reporter_name')
                            ->label('Reporter Name')
                            ->required(fn (): bool => ! $this->reporting()->outstanding?->location?->is_ho)
                            ->maxLength(255)
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        DatePicker::make('date_visit')
                            ->label('Tanggal Aksi')
                            ->native(false)
                            ->date()
                            ->required()
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        ToggleButtons::make('work')
                            ->label('Tipe Aksi')
                            ->options([
                                'visit' => 'Visit',
                                'remote' => 'Remote',
                            ])
                            ->in(['visit', 'remote'])
                            ->inline()
                            ->grouped()
                            ->required()
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        Select::make('support_user_ids')
                            ->label('Support')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->exists('users', 'id')
                            ->options(fn (): array => User::query()
                                ->where(function ($query): void {
                                    $query
                                        ->where('status', '!=', 0)
                                        ->orWhereIn('id', $this->reporting()->users()->pluck('users.id'));
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        Select::make('status')
                            ->label('Status')
                            ->options(ReportStatus::class)
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->enum(ReportStatus::class)
                            ->required()
                            ->disabled(fn (): bool => $this->isStatusLocked())
                            ->helperText(fn (): ?string => $this->reporting()->outstanding?->location?->is_ho
                                ? 'Lokasi HO — status terkunci Finish.'
                                : null),
                        Select::make('pending_reason')
                            ->label('Alasan Pending')
                            ->options(PendingReason::class)
                            ->native(false)
                            ->enum(PendingReason::class)
                            ->visible(fn (Get $get): bool => PendingReason::isRequiredFor($get('status')))
                            ->required(fn (Get $get): bool => PendingReason::isRequiredFor($get('status')))
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        DatePicker::make('revisit')
                            ->label('Revisit')
                            ->placeholder('Date next target')
                            ->native(true)
                            ->visible(fn (Get $get): bool => ! $this->reporting()->outstanding?->location?->is_ho
                                && $this->normalizeStatus($get('status')) !== ReportStatus::Finish)
                            ->required(fn (Get $get): bool => ! $this->reporting()->outstanding?->location?->is_ho
                                && $this->normalizeStatus($get('status')) !== ReportStatus::Finish)
                            ->disabled(fn (): bool => ! $this->canEditTicketDetails()),
                        TextEntry::make('send_mail_at')
                            ->label('Send Mail At')
                            ->date('d M Y'),
                    ])
                    ->columns(1),
            ]);
    }

    protected function reporting(): Reporting
    {
        $record = $this->record;

        if (! $record instanceof Reporting) {
            throw new \LogicException('SendEmailReporting expects a Reporting record.');
        }

        return $record;
    }

    /**
     * URL foto pada media collection "form_support" (lihat SupportReportingForm).
     *
     * @return array<int, string>
     */
    protected function getFormSupportUrls(): array
    {
        return $this->reporting()
            ->getMedia('form_support')
            ->map(fn (Media $media): string => $media->getUrl())
            ->all();
    }

    protected function normalizeStatus(mixed $state): ?ReportStatus
    {
        return $state instanceof ReportStatus
            ? $state
            : ReportStatus::tryFrom((string) $state);
    }

    /**
     * Status lokasi HO dipaksa Finish (lihat EditSupportReporting::mutateFormDataBeforeSave).
     */
    protected function isStatusLocked(): bool
    {
        return (bool) $this->reporting()->outstanding?->location?->is_ho
            || ! $this->canEditTicketDetails();
    }

    public function canEditTicketDetails(): bool
    {
        return (bool) auth()->user()?->can('update', $this->reporting());
    }

    public function saveTicketDetails(): void
    {
        $reporting = $this->reporting();
        Gate::authorize('update', $reporting);

        $data = $this->ticketDetailsForm->getState();
        $outstanding = $reporting->outstanding;

        if (! $outstanding) {
            Notification::make()
                ->title('Detail tiket tidak dapat disimpan.')
                ->body('Outstanding terkait tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        $status = $outstanding->location?->is_ho
            ? ReportStatus::Finish
            : $this->normalizeStatus($data['status'] ?? null);

        if (! $status) {
            return;
        }

        $revisit = $status === ReportStatus::Finish ? null : ($data['revisit'] ?? null);

        DB::transaction(function () use ($data, $outstanding, $reporting, $status, $revisit): void {
            $reporting->update([
                'date_visit' => $data['date_visit'],
                'work' => $data['work'],
                'status' => $status,
                'pending_reason' => $data['pending_reason'] ?? null,
                'revisit' => $revisit,
            ]);

            $outstanding->update([
                'title' => $data['title'],
                'reporter' => $data['reporter'] ?? null,
                'reporter_name' => $data['reporter_name'] ?? null,
            ]);

            $reporting->users()->sync($data['support_user_ids'] ?? []);
            $reporting->syncOutstandingStatus();
        });

        CalculateSupportTravelDistance::dispatch($reporting->id);

        $this->record->refresh()->load(['outstanding.location.team', 'outstanding.location.company', 'users']);
        $this->ticketDetailsForm->fill([
            'title' => $this->record->outstanding?->title,
            'reporter' => $this->record->outstanding?->reporter,
            'reporter_name' => $this->record->outstanding?->reporter_name,
            'date_visit' => $this->record->date_visit,
            'work' => $this->record->work,
            'support_user_ids' => $this->record->users->modelKeys(),
            'status' => $this->record->status?->value,
            'pending_reason' => $this->record->pending_reason?->value,
            'revisit' => $this->record->revisit,
        ]);

        Notification::make()
            ->title('Perubahan detail tiket berhasil disimpan.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ReportingEmail::query()->where('reporting_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('send_mail_at')
                    ->label('Terkirim Pada')
                    ->dateTime('d M Y H:i'),
                Tables\Columns\TextColumn::make('email_to')
                    ->label('Email To')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                Tables\Columns\TextColumn::make('email_cc')
                    ->label('Email CC')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
            ])
            ->headerActions([
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => $this->resetTable()),
            ]);
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $reporting = $this->record;

        $emailTo = $data['email_to'] ?? [];
        $emailCc = $data['email_cc'] ?? [];

        if (empty($emailTo)) {
            Notification::make()
                ->title('Email penerima (To) tidak boleh kosong.')
                ->danger()
                ->send();

            return;
        }

        // Update the reporting record with edited form data
        $reporting->update([
            'cause' => $data['cause'] ?? $reporting->cause,
            'action' => $data['action'] ?? $reporting->action,
            'note' => $data['note'] ?? $reporting->note,
            'start_work' => $data['start_work'] ?? $reporting->start_work,
            'end_work' => $data['end_work'] ?? $reporting->end_work,
            'send_mail_at' => $reporting->send_mail_at ?? now(),
        ]);

        $reportingEmail = ReportingEmail::create([
            'reporting_id' => $reporting->id,
            'cause' => $data['cause'] ?? $reporting->cause,
            'action' => $data['action'] ?? $reporting->action,
            'note' => $data['note'] ?? $reporting->note,
            'start_work' => $data['start_work'] ?? $reporting->start_work,
            'end_work' => $data['end_work'] ?? $reporting->end_work,
            'email_to' => $emailTo,
            'email_cc' => $emailCc,
            'send_mail_at' => now(),
        ]);

        // Copy existing attachments or add new ones
        foreach ($data['attachments'] ?? [] as $attachment) {
            if (is_string($attachment)) {
                $media = Media::findByUuid($attachment);
                if ($media) {
                    $reportingEmail->copyMedia($media->getPath())->toMediaCollection('attachments');
                }
            } elseif ($attachment instanceof TemporaryUploadedFile) {
                $reportingEmail->addMedia($attachment->getRealPath())
                    ->usingName($attachment->getClientOriginalName())
                    ->usingFileName($attachment->getClientOriginalName())
                    ->toMediaCollection('attachments');
            }
        }

        $reporting->load(['outstanding.location.team', 'outstanding.location.company', 'users', 'media']);

        $mail = Mail::to($emailTo);
        if (! empty($emailCc)) {
            $mail->cc($emailCc);
        }

        $mail->queue(new SupportReportingMail($reportingEmail, false));

        Notification::make()
            ->title('Email berhasil dikirim!')
            ->success()
            ->send();

        $this->redirect(SupportReportingResource::getUrl('index', ['record' => $reporting]));
    }
}

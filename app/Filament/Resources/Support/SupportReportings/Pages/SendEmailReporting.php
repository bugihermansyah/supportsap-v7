<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use App\Mail\SupportReportingMail;
use App\Models\Customer;
use App\Models\Reporting;
use App\Models\ReportingEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Facades\Mail;

class SendEmailReporting extends Page implements HasForms, HasTable
{
    use InteractsWithRecord;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = SupportReportingResource::class;

    protected string $view = 'filament.pages.support-reportings.send-email';

    public ?array $data = [];

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
            'start_work' =>$this->record->start_work,
            'end_work' =>$this->record->end_work,
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
                Section::make('Detail Tiket')
                    ->schema([
                        TextEntry::make('outstanding.number')
                            ->label('No. Tiket'),
                        TextEntry::make('outstanding.location.name')
                            ->label('Lokasi'),
                        TextEntry::make('outstanding.title')
                            ->label('Masalah'),
                        TextEntry::make('outstanding.reporter')
                            ->label('Reporter'),
                        TextEntry::make('date_visit')
                            ->label('Tanggal Aksi')
                            ->date('d M Y'),
                        TextEntry::make('work')
                            ->label('Tipe Aksi')
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('users.firstname')
                            ->label('Support'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('send_mail_at')
                            ->label('Send Mail At')
                            ->date('d M Y'),
                        TextEntry::make('revisit')
                            ->label('Revisit')
                            ->date('d M Y')
                            ->visible(fn (Reporting $record): bool => filled($record->revisit)),
                    ])
                    ->columnSpan(1),
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
                                    ->required()
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
                        ])
                        ->columnSpan(1),
            ])
            ->columns(4);
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
                $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::findByUuid($attachment);
                if ($media) {
                    $reportingEmail->copyMedia($media->getPath())->toMediaCollection('attachments');
                }
            } elseif ($attachment instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
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

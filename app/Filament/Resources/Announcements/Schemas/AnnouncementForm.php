<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Enums\AnnouncementLevel;
use App\Models\QuizSession;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Isi pengumuman')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->label('Isi')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Gambar (opsional)')
                            ->collection('image')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            // Dikompres sebelum disimpan.
                            ->optimize('jpg', 70)
                            ->maxImageWidth(1360)
                            ->imageEditor()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Penayangan')
                    ->columns(2)
                    ->components([
                        Select::make('level')
                            ->label('Tingkat')
                            ->options(AnnouncementLevel::class)
                            ->default(AnnouncementLevel::Info->value)
                            ->required(),
                        Select::make('target_roles')
                            ->label('Ditujukan ke role')
                            ->helperText('Kosongkan = semua role.')
                            ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name'))
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('starts_at')
                            ->label('Mulai tayang')
                            ->helperText('Kosongkan = langsung tayang.')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('d M Y H:i'),
                        DateTimePicker::make('ends_at')
                            ->label('Berhenti tayang')
                            ->helperText('Kosongkan = tayang terus.')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->rules(['nullable', 'after:starts_at'])
                            ->validationMessages([
                                'after' => 'Tanggal berhenti harus setelah tanggal mulai.',
                            ]),
                        Select::make('quiz_session_id')
                            ->label('Tautkan ke sesi quiz (opsional)')
                            ->helperText('Kalau diisi, pengumuman menampilkan tombol "Kerjakan quiz".')
                            ->options(fn () => QuizSession::query()
                                ->orderByDesc('starts_at')
                                ->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Publikasikan')
                            ->default(true),
                        Toggle::make('is_pinned')
                            ->label('Sematkan di atas')
                            ->default(false),
                    ]),
            ]);
    }
}

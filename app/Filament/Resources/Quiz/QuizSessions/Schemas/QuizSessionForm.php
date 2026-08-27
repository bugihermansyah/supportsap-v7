<?php

namespace App\Filament\Resources\Quiz\QuizSessions\Schemas;

use App\Models\QuizQuestion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class QuizSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sesi')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label('Judul sesi')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label('Quiz dibuka')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->default(now()->startOfDay())
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Quiz ditutup')
                            ->seconds(false)
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->default(now()->endOfDay())
                            ->required()
                            ->rules(['after:starts_at'])
                            ->validationMessages([
                                'after' => 'Tanggal tutup harus setelah tanggal buka.',
                            ]),
                        Textarea::make('description')
                            ->label('Keterangan (opsional)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Publikasikan')
                            ->helperText('Sesi hanya muncul ke peserta bila dipublikasikan dan berada dalam rentang tanggal.')
                            ->default(false),
                    ]),
                Section::make('Soal dalam sesi')
                    ->description('Pilih soal dari bank soal. Urutan soal diacak otomatis untuk setiap peserta.')
                    ->components([
                        Select::make('questions')
                            ->hiddenLabel()
                            ->relationship(
                                name: 'questions',
                                titleAttribute: 'question',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->with('product'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (QuizQuestion $record): string => ($record->product?->name ? '['.$record->product->name.'] ' : '').Str::limit($record->question, 90))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Soal non-aktif tidak ditampilkan di daftar ini.'),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Quiz\QuizQuestions\Schemas;

use App\Models\Product;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuizQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Soal')
                    ->columns(2)
                    ->components([
                        Select::make('product_id')
                            ->label('Kategori (Produk)')
                            ->helperText('Dipakai untuk pengelompokan nilai / analisis per produk.')
                            ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Soal non-aktif tidak bisa dipilih ke sesi baru.')
                            ->default(true),
                        Textarea::make('question')
                            ->label('Pertanyaan')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label('Gambar soal (opsional)')
                            ->collection('image')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            // Dikompres sebelum disimpan (mengikuti pola upload lain di app ini).
                            ->optimize('jpg', 70)
                            ->maxImageWidth(1360)
                            ->imageEditor()
                            ->openable()
                            ->columnSpanFull(),
                        Textarea::make('explanation')
                            ->label('Pembahasan (opsional)')
                            ->helperText('Ditampilkan ke peserta setelah quiz selesai.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Pilihan jawaban')
                    ->description('Minimal 2 pilihan, dan tepat 1 pilihan ditandai benar.')
                    ->components([
                        Repeater::make('options')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->defaultItems(4)
                            ->minItems(2)
                            ->maxItems(6)
                            ->table([
                                TableColumn::make('Jawaban'),
                                TableColumn::make('Benar')
                                    ->width('80px'),
                            ])
                            ->schema([
                                TextInput::make('option_text')
                                    ->hiddenLabel()
                                    ->required(),
                                Toggle::make('is_correct')
                                    ->hiddenLabel()
                                    ->inline(false)
                                    ->default(false),
                            ])
                            ->rule(static function (): Closure {
                                return static function (string $attribute, $value, Closure $fail): void {
                                    $correct = collect($value)->filter(fn ($option) => filled($option['is_correct'] ?? null) && $option['is_correct'])->count();

                                    if ($correct !== 1) {
                                        $fail('Tandai tepat 1 pilihan sebagai jawaban benar (saat ini: '.$correct.').');
                                    }
                                };
                            }),
                    ]),
            ]);
    }
}

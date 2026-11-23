<?php

namespace App\Filament\Resources\SupportHolidays;

use App\Enums\SupportHolidayType;
use App\Enums\SupportIncentiveCategory;
use App\Filament\Resources\SupportHolidays\Pages\ManageSupportHolidays;
use App\Models\SupportHoliday;
use App\Services\SupportIncentiveRates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SupportHolidayResource extends Resource
{
    protected static ?string $model = SupportHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Main';

    protected static ?string $navigationLabel = 'Kalender Insentif';

    protected static ?string $modelLabel = 'Tanggal Libur';

    protected static ?string $pluralModelLabel = 'Kalender Insentif';

    protected static ?int $navigationSort = 18;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('holiday_date')
                ->label('Tanggal')
                ->native(true)
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->label('Nama hari libur')
                ->required()
                ->maxLength(160),
            Select::make('category')
                ->label('Kategori tarif')
                ->options(SupportHolidayType::class)
                ->native(false)
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('holiday_date')
            ->columns([
                TextColumn::make('holiday_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Hari libur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(SupportHolidayType::class),
            ])
            ->headerActions([
                Action::make('manageRates')
                    ->label('Atur Tarif')
                    ->icon('heroicon-o-currency-dollar')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('update_support_holiday'))
                    ->schema([
                        Section::make('Tarif insentif')
                            ->description('Tarif berlaku satu kali untuk setiap support per tanggal kerja.')
                            ->columns(3)
                            ->schema([
                                TextInput::make('rate_weekend')
                                    ->label(SupportIncentiveCategory::Weekend->getLabel())
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->step(1000)
                                    ->rules(['integer', 'min:0'])
                                    ->required(),
                                TextInput::make('rate_public_holiday')
                                    ->label(SupportIncentiveCategory::PublicHoliday->getLabel())
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->step(1000)
                                    ->rules(['integer', 'min:0'])
                                    ->required(),
                                TextInput::make('rate_major_holiday')
                                    ->label(SupportIncentiveCategory::MajorHoliday->getLabel())
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->step(1000)
                                    ->rules(['integer', 'min:0'])
                                    ->required(),
                            ]),
                        Section::make('Penerapan tarif')
                            ->description('Kandidat yang masih menunggu verifikasi akan diperbarui. Insentif yang sudah disetujui atau ditolak tetap menyimpan tarif sebelumnya.'),
                    ])
                    ->fillForm(fn (): array => app(SupportIncentiveRates::class)->currentSettings())
                    ->modalHeading('Atur Tarif Insentif')
                    ->modalSubmitActionLabel('Simpan Tarif')
                    ->action(function (array $data): void {
                        app(SupportIncentiveRates::class)->saveSettings($data);

                        Notification::make()
                            ->success()
                            ->title('Tarif insentif disimpan')
                            ->body('Tarif kandidat yang masih menunggu verifikasi juga telah diperbarui.')
                            ->send();
                    }),
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'created_by' => auth()->id(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSupportHolidays::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Support\SupportReportings;

use App\Filament\Resources\Support\SupportReportings\Pages\CreateSupportReporting;
use App\Filament\Resources\Support\SupportReportings\Pages\EditSupportReporting;
use App\Filament\Resources\Support\SupportReportings\Pages\ListSupportReportings;
use App\Filament\Resources\Support\SupportReportings\Pages\SendEmailReporting;
use App\Filament\Resources\Support\SupportReportings\Pages\ViewSupportReporting;
use App\Filament\Resources\Support\SupportReportings\Schemas\SupportReportingForm;
use App\Filament\Resources\Support\SupportReportings\Schemas\SupportReportingInfolist;
use App\Filament\Resources\Support\SupportReportings\Tables\SupportReportingsTable;
use App\Models\Reporting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportReportingResource extends Resource
{
    protected static ?string $model = Reporting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Work';

    // protected static ?string $recordTitleAttribute = 'location_title';

    protected static ?string $modelLabel = 'Daily Reporting';

    protected static ?string $navigationLabel = 'Daily Reporting';

    public static function getEvaluationFormSchema(): array
    {
        return [
            Section::make('KPI Score')
                ->description('Penyesuaian komponen KPI untuk menghasilkan nilai skor.')
                ->schema([
                    Toggle::make('is_ho')
                        ->label('Is HO Visit? (Auto 100)')
                        ->live(),
                    
                    Toggle::make('has_photo')
                        ->label('Has Photo Attachment')
                        ->live(),
                        
                    Toggle::make('has_form')
                        ->label('Has Form Support')
                        ->live(),
                    
                    Toggle::make('is_sameday')
                        ->label('Same Day Progress')
                        ->live(),
                    
                    TextInput::make('days_late')
                        ->label('Days Late')
                        ->numeric()
                        ->live(),
                        
                    Select::make('level')
                        ->label('Difficulty Level')
                        ->options([
                            1 => 'Very Easy (1)',
                            2 => 'Easy (2)',
                            3 => 'Normal (3)',
                            4 => 'Hard (4)',
                            5 => 'Very Hard (5)'
                        ])
                        ->live(),

                    \Filament\Infolists\Components\TextEntry::make('calculated_score')
                        ->label('Final Score (Live Calculation)')
                        ->state(function (Get $get) {
                            return self::calculateSimulatedScore(
                                $get('is_ho'),
                                $get('has_photo'),
                                $get('has_form'),
                                $get('days_late'),
                                $get('is_sameday'),
                                $get('level')
                            );
                        })
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold)
                        ->color(function ($state) {
                            $finalScore = (int) $state;
                            return $finalScore >= 80 ? 'success' : ($finalScore >= 60 ? 'warning' : 'danger');
                        }),
                ])->columns(2),

            Textarea::make('evaluation_note')
                ->label('Evaluation Note')
                ->placeholder('Optional notes regarding the score...')
                ->maxLength(255)
        ];
    }

    public static function calculateSimulatedScore($isHo, $hasPhoto, $hasForm, $daysLate, $isSameday, $level): int
    {
        if ($isHo) return 100;
        
        $score = (int) safe_db_config('general.kpi_base_score', 100);
        $level = (int) $level;
        $graceDays = $level >= 4 ? 1 : 0;
        
        $daysLate = (int) $daysLate;
        $effectiveLate = max(0, $daysLate - $graceDays);
        
        if ($effectiveLate >= 3) {
            $score -= (int) safe_db_config('general.kpi_late_penalty_h3', 50);
        } elseif ($effectiveLate == 2) {
            $score -= (int) safe_db_config('general.kpi_late_penalty_h2', 20);
        } elseif ($effectiveLate == 1) {
            $score -= (int) safe_db_config('general.kpi_late_penalty_h1', 10);
        }
        
        if (!$hasPhoto) $score -= (int) safe_db_config('general.kpi_no_photo_penalty', 15);
        if (!$hasForm) $score -= (int) safe_db_config('general.kpi_no_form_penalty', 30);
        
        if ($isSameday) $score += (int) safe_db_config('general.kpi_sameday_bonus', 15);
        
        $levelBonus = match ($level) {
            5 => (int) safe_db_config('general.kpi_bonus_very_hard', 15),
            4 => (int) safe_db_config('general.kpi_bonus_hard', 10),
            default => 0,
        };
        $score += $levelBonus;
        
        return max(0, $score);
    }

    public static function getEvaluationFillFormCallback(): \Closure
    {
        return function ($record): array {
            $visitDate = \Carbon\Carbon::parse($record->date_visit ?? now())->startOfDay();
            $inputDate = $record->created_at ? $record->created_at->startOfDay() : now()->startOfDay();
            $daysLate = max(0, $inputDate->diffInDays($visitDate));
            
            $outstandingDateIn = $record->outstanding?->date_in ? \Carbon\Carbon::parse($record->outstanding->date_in)->startOfDay() : null;
            $isSameday = $outstandingDateIn && $visitDate->eq($outstandingDateIn);

            return [
                'is_ho' => $record->outstanding?->location?->is_ho ?? false,
                'has_photo' => $record->getMedia('attachments')->count() > 0,
                'has_form' => $record->getMedia('form_support')->count() > 0,
                'days_late' => $daysLate,
                'is_sameday' => $isSameday,
                'level' => $record->outstanding?->level ?? 3,
                'evaluation_note' => $record->evaluation_note,
            ];
        };
    }

    public static function getEvaluationActionCallback(): \Closure
    {
        return function (array $data, $record): void {
            $finalScore = self::calculateSimulatedScore(
                $data['is_ho'],
                $data['has_photo'],
                $data['has_form'],
                $data['days_late'],
                $data['is_sameday'],
                $data['level']
            );

            $record->update([
                'score' => $finalScore,
                'evaluation_note' => $data['evaluation_note'],
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Evaluation updated successfully')
                ->success()
                ->send();
        };
    }

    public static function form(Schema $schema): Schema
    {
        return SupportReportingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportReportingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportReportingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportReportings::route('/'),
            'create' => CreateSupportReporting::route('/create'),
            'view' => ViewSupportReporting::route('/{record}'),
            'send-email' => SendEmailReporting::route('/{record}/send-email'),
            'edit' => EditSupportReporting::route('/{record}/edit'),
        ];
    }
}

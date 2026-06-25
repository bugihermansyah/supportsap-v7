<?php

namespace App\Livewire;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class KpiSimulator extends MyProfileComponent
{
    protected string $view = 'livewire.kpi-simulator';

    public static $sort = 20;

    public ?array $data = [];
    public ?array $simResult = null;

    public function mount(): void
    {
        $this->form->fill([
            'sim_level' => 3,
            'sim_days_late' => 0,
            'sim_has_photo' => false,
            'sim_has_form' => false,
            'sim_sameday' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('sim_level')
                    ->label('Level Outstanding')
                    ->options([
                        1 => 'Very Easy',
                        2 => 'Easy',
                        3 => 'Normal',
                        4 => 'Hard',
                        5 => 'Very Hard',
                    ])
                    ->required(),

                TextInput::make('sim_days_late')
                    ->label('Keterlambatan reporting')
                    ->minValue(0)
                    ->numeric()
                    ->required(),

                Toggle::make('sim_has_photo')
                    ->label('Ada Foto?')
                    ->inline(false),

                Toggle::make('sim_has_form')
                    ->label('Ada Form Support?')
                    ->inline(false),

                Toggle::make('sim_sameday')
                    ->label('Dikerjakan di Hari H?')
                    ->inline(false),

                ViewField::make('sim_result_view')
                    ->view('filament.pages.partials.kpi-simulation-result')
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function simulateScore(): void
    {
        $state = $this->form->getState();

        $baseScore = (int) safe_db_config('general.kpi_base_score', 100);
        $latePenaltyH1 = (int) safe_db_config('general.kpi_late_penalty_h1', 10);
        $latePenaltyH2 = (int) safe_db_config('general.kpi_late_penalty_h2', 20);
        $latePenaltyH3 = (int) safe_db_config('general.kpi_late_penalty_h3', 50);
        $noPhotoPenalty = (int) safe_db_config('general.kpi_no_photo_penalty', 15);
        $noFormPenalty = (int) safe_db_config('general.kpi_no_form_penalty', 30);
        $samedayBonus = (int) safe_db_config('general.kpi_sameday_bonus', 15);
        $bonusVeryHard = (int) safe_db_config('general.kpi_bonus_very_hard', 15);
        $bonusHard = (int) safe_db_config('general.kpi_bonus_hard', 10);

        $gradeAPlus = (int) safe_db_config('general.kpi_grade_a_plus_min', 101);
        $gradeA = (int) safe_db_config('general.kpi_grade_a_min', 85);
        $gradeB = (int) safe_db_config('general.kpi_grade_b_min', 70);
        $gradeC = (int) safe_db_config('general.kpi_grade_c_min', 50);

        $level = (int) ($state['sim_level'] ?? 3);
        $daysLate = (int) ($state['sim_days_late'] ?? 0);
        $hasPhoto = (bool) ($state['sim_has_photo'] ?? false);
        $hasForm = (bool) ($state['sim_has_form'] ?? false);
        $sameday = (bool) ($state['sim_sameday'] ?? false);

        $score = $baseScore;
        $breakdown = [];
        $breakdown[] = ['label' => 'Skor Dasar', 'value' => $baseScore, 'type' => 'base'];

        // Lateness
        $graceDays = $level >= 4 ? 1 : 0;
        $effectiveLate = max(0, $daysLate - $graceDays);

        if ($effectiveLate >= 3) {
            $score -= $latePenaltyH3;
            $breakdown[] = ['label' => "Keterlambatan >= H+3 (Efektif {$effectiveLate} hari)", 'value' => -$latePenaltyH3, 'type' => 'penalty'];
        } elseif ($effectiveLate == 2) {
            $score -= $latePenaltyH2;
            $breakdown[] = ['label' => "Keterlambatan H+2 (Efektif {$effectiveLate} hari)", 'value' => -$latePenaltyH2, 'type' => 'penalty'];
        } elseif ($effectiveLate == 1) {
            $score -= $latePenaltyH1;
            $breakdown[] = ['label' => "Keterlambatan H+1 (Efektif {$effectiveLate} hari)", 'value' => -$latePenaltyH1, 'type' => 'penalty'];
        } elseif ($daysLate > 0 && $graceDays > 0) {
            $breakdown[] = ['label' => "Keterlambatan {$daysLate} hari (dalam toleransi {$graceDays} hari)", 'value' => 0, 'type' => 'neutral'];
        }

        // Incomplete report
        if (!$hasPhoto) {
            $score -= $noPhotoPenalty;
            $breakdown[] = ['label' => 'Tidak ada foto', 'value' => -$noPhotoPenalty, 'type' => 'penalty'];
        }

        if (!$hasForm) {
            $score -= $noFormPenalty;
            $breakdown[] = ['label' => 'Tidak ada form support', 'value' => -$noFormPenalty, 'type' => 'penalty'];
        }

        // Sameday progress
        if ($sameday) {
            $score += $samedayBonus;
            $breakdown[] = ['label' => 'Dikerjakan Hari H', 'value' => +$samedayBonus, 'type' => 'bonus'];
        }

        // Level bonus
        $levelLabels = [1 => 'Very Easy', 2 => 'Easy', 3 => 'Normal', 4 => 'Hard', 5 => 'Very Hard'];
        $levelBonus = match ($level) {
            5 => $bonusVeryHard,
            4 => $bonusHard,
            default => 0,
        };
        if ($levelBonus > 0) {
            $score += $levelBonus;
            $breakdown[] = ['label' => "Bonus level {$levelLabels[$level]}", 'value' => +$levelBonus, 'type' => 'bonus'];
        }

        $finalScore = max(0, $score);

        // Grade
        $grade = 'D';
        if ($finalScore >= $gradeAPlus) $grade = 'A+';
        elseif ($finalScore >= $gradeA) $grade = 'A';
        elseif ($finalScore >= $gradeB) $grade = 'B';
        elseif ($finalScore >= $gradeC) $grade = 'C';

        $this->simResult = [
            'breakdown' => $breakdown,
            'rawScore' => $score,
            'finalScore' => $finalScore,
            'grade' => $grade,
            'levelLabel' => $levelLabels[$level] ?? 'Normal',
        ];
    }
}

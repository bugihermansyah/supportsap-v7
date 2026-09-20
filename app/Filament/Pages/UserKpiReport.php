<?php

namespace App\Filament\Pages;

use App\Models\Reporting;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class UserKpiReport extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.pages.user-kpi-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Work';

    protected static ?string $navigationLabel = 'KPI Raport';

    public ?string $user_id = null;

    public ?string $month = null;

    public $reportings = [];

    public $averageScore = 0;

    public $grade = '-';

    public $totalCases = 0;

    public $selectedUser = null;

    public function mount(): void
    {
        // Dipanggil dari halaman Support Performance dengan ?user_id=... — kalau ada,
        // formnya langsung terisi dan raportnya ditampilkan tanpa klik lagi.
        $requested = request()->query('user_id');
        $requested = is_string($requested) && $requested !== '' ? $requested : null;

        $this->form->fill([
            'user_id' => $requested,
            'month' => Carbon::now()->format('Y-m'),
        ]);

        if ($requested !== null) {
            $this->generateReport();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Select Support User')
                    ->options(User::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('month')
                    ->label('Select Month')
                    ->options(function () {
                        $options = [];
                        for ($i = 0; $i < 40; $i++) {
                            $date = Carbon::now()->subMonths($i);
                            $options[$date->format('Y-m')] = $date->format('F Y');
                        }

                        return $options;
                    })
                    ->required()
                    ->default(Carbon::now()->format('Y-m')),
            ])
            ->columns(2);
    }

    public function generateReport()
    {
        $data = $this->form->getState();
        $this->user_id = $data['user_id'];
        $this->month = $data['month'];

        $this->selectedUser = User::find($this->user_id);

        $dateParts = explode('-', $this->month);
        $year = $dateParts[0];
        $month = $dateParts[1];

        // Get reportings for this user in this month
        $this->reportings = Reporting::whereHas('users', function ($query) {
            $query->where('user_id', $this->user_id);
        })
            ->whereYear('date_visit', $year)
            ->whereMonth('date_visit', $month)
            ->with('outstanding.location') // Eager load
            ->orderBy('date_visit', 'desc')
            ->get();

        $this->totalCases = $this->reportings->count();

        if ($this->totalCases > 0) {
            // Calculate average score ignoring nulls
            $scoredReportings = $this->reportings->whereNotNull('score');
            if ($scoredReportings->count() > 0) {
                $this->averageScore = round($scoredReportings->avg('score'), 2);
            } else {
                $this->averageScore = 0; // Or keep it null, but 0 makes sense if no graded reportings
            }
        } else {
            $this->averageScore = 0;
        }

        $this->grade = $this->calculateGrade($this->averageScore);
    }

    private function calculateGrade($score)
    {
        if ($this->totalCases === 0) {
            return '-';
        }
        if ($this->reportings->whereNotNull('score')->count() === 0) {
            return 'Not Graded';
        }

        $gradeAPlus = (int) safe_db_config('general.kpi_grade_a_plus_min', 101);
        $gradeA = (int) safe_db_config('general.kpi_grade_a_min', 85);
        $gradeB = (int) safe_db_config('general.kpi_grade_b_min', 70);
        $gradeC = (int) safe_db_config('general.kpi_grade_c_min', 50);

        if ($score >= $gradeAPlus) {
            return 'A+';
        }
        if ($score >= $gradeA) {
            return 'A';
        }
        if ($score >= $gradeB) {
            return 'B';
        }
        if ($score >= $gradeC) {
            return 'C';
        }

        return 'D';
    }
}

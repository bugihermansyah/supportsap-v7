<?php

namespace App\Exports;

use App\Enums\SupportIncentiveStatus;
use App\Models\SupportIncentive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupportIncentiveExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array<string, mixed> $filters */
    public function __construct(private readonly array $filters) {}

    public function collection(): Collection
    {
        $dateRange = $this->filters['work_date'] ?? [];

        return SupportIncentive::query()
            ->where('status', SupportIncentiveStatus::Approved->value)
            ->when($dateRange['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('work_date', '>=', $date))
            ->when($dateRange['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('work_date', '<=', $date))
            ->when($this->filters['support_user_id']['value'] ?? null, fn (Builder $query, string $userId): Builder => $query->where('support_user_id', $userId))
            ->when($this->filters['team_id']['value'] ?? null, fn (Builder $query, string $teamId): Builder => $query->whereHas('supportUser', fn (Builder $userQuery) => $userQuery->where('team_id', $teamId)))
            ->when($this->filters['category']['value'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->with(['supportUser.team', 'reporting.outstanding.location'])
            ->orderBy('work_date')
            ->orderBy('support_user_id')
            ->get();
    }

    public function headings(): array
    {
        return ['Tanggal', 'Support', 'Tim', 'Lokasi Bukti', 'Kategori', 'Hari Libur', 'Insentif', 'Status'];
    }

    public function map($row): array
    {
        return [
            Date::dateTimeToExcel($row->work_date),
            $row->supportUser?->name,
            $row->supportUser?->team?->name ?? 'HO',
            $row->reporting?->outstanding?->location?->name,
            $row->category?->getLabel(),
            $row->holiday_name,
            $row->rate,
            $row->status?->getLabel(),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => 'dd mmm yyyy',
            'G' => '"Rp" #,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '5C504D'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Insentif Support';
    }
}

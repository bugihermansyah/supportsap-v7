<?php

namespace App\Services;

use App\Enums\SupportHolidayType;
use App\Enums\SupportIncentiveCategory;
use App\Enums\SupportIncentiveStatus;
use App\Models\Reporting;
use App\Models\SupportHoliday;
use App\Models\SupportIncentive;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportIncentiveClaimService
{
    public function __construct(private readonly SupportIncentiveRates $rates) {}

    /** @return array<string, string> */
    public function availableReportOptions(User $support): array
    {
        return $this->eligibleReportingQuery($support)
            ->with('outstanding.location')
            ->orderByDesc('date_visit')
            ->get()
            ->mapWithKeys(fn (Reporting $reporting): array => [
                $reporting->id => CarbonImmutable::parse($reporting->date_visit)->format('d M Y')
                    .' - '.($reporting->outstanding?->location?->name ?? 'Lokasi tidak diketahui')
                    .' - '.($reporting->outstanding?->title ?? 'Outstanding'),
            ])
            ->all();
    }

    public function submit(User $support, string $reportingId): SupportIncentive
    {
        abort_unless($support->hasAnyRole(SupportIncentive::ELIGIBLE_SUPPORT_ROLES), 403);

        return DB::transaction(function () use ($support, $reportingId): SupportIncentive {
            $reporting = $this->eligibleReportingQuery($support)
                ->whereKey($reportingId)
                ->with('outstanding.location')
                ->first();

            if (! $reporting) {
                throw ValidationException::withMessages([
                    'reporting_id' => 'Reporting tidak tersedia, belum memiliki Form Support, atau sudah diajukan untuk tanggal tersebut.',
                ]);
            }

            $date = CarbonImmutable::parse($reporting->date_visit);
            $holiday = SupportHoliday::query()
                ->whereDate('holiday_date', $date->toDateString())
                ->first();
            $category = $this->categoryFor($date, $holiday);

            if (! $category) {
                throw ValidationException::withMessages([
                    'reporting_id' => 'Tanggal reporting bukan akhir pekan atau tanggal libur yang terdaftar.',
                ]);
            }

            $incentive = SupportIncentive::query()->firstOrNew([
                'support_user_id' => $support->id,
                'work_date' => $date->toDateString(),
            ]);

            if ($incentive->exists) {
                throw ValidationException::withMessages([
                    'reporting_id' => 'Insentif untuk tanggal ini sudah pernah diajukan.',
                ]);
            }

            $incentive->fill([
                'reporting_id' => $reporting->id,
                'category' => $category,
                'holiday_name' => $holiday?->name,
                'rate' => $this->rates->forCategory($category),
                'status' => SupportIncentiveStatus::Pending,
            ]);
            $incentive->save();

            return $incentive;
        });
    }

    private function eligibleReportingQuery(User $support): Builder
    {
        return Reporting::query()
            ->where('work', 'visit')
            ->whereNotNull('status')
            ->whereHas('users', fn (Builder $query) => $query
                ->where('users.id', $support->id)
                ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', SupportIncentive::ELIGIBLE_SUPPORT_ROLES)))
            ->whereHas('media', fn (Builder $query) => $query->where('collection_name', 'form_support'))
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw('DAYOFWEEK(reportings.date_visit) IN (1, 7)')
                    ->orWhereIn('reportings.date_visit', SupportHoliday::query()->select('holiday_date'));
            })
            ->whereNotIn('date_visit', SupportIncentive::query()
                ->select('work_date')
                ->where('support_user_id', $support->id));
    }

    private function categoryFor(CarbonImmutable $date, ?SupportHoliday $holiday): ?SupportIncentiveCategory
    {
        if ($holiday?->category === SupportHolidayType::MajorHoliday) {
            return SupportIncentiveCategory::MajorHoliday;
        }

        if ($holiday) {
            return SupportIncentiveCategory::PublicHoliday;
        }

        return $date->isWeekend() ? SupportIncentiveCategory::Weekend : null;
    }
}

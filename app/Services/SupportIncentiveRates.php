<?php

namespace App\Services;

use App\Enums\SupportIncentiveCategory;
use App\Enums\SupportIncentiveStatus;
use App\Models\SupportIncentive;
use Inerba\DbConfig\DbConfig;
use Illuminate\Support\Facades\DB;

class SupportIncentiveRates
{
    /** @var array<string, int> */
    public const DEFAULTS = [
        'rate_weekend' => 150_000,
        'rate_public_holiday' => 200_000,
        'rate_major_holiday' => 250_000,
    ];

    public function forCategory(SupportIncentiveCategory $category): int
    {
        $key = match ($category) {
            SupportIncentiveCategory::Weekend => 'rate_weekend',
            SupportIncentiveCategory::PublicHoliday => 'rate_public_holiday',
            SupportIncentiveCategory::MajorHoliday => 'rate_major_holiday',
        };

        return max(0, (int) DbConfig::get("support_incentive.{$key}", self::DEFAULTS[$key]));
    }

    /** @return array<string, int> */
    public function currentSettings(): array
    {
        return collect(self::DEFAULTS)
            ->map(fn (int $default, string $key): int => (int) DbConfig::get("support_incentive.{$key}", $default))
            ->all();
    }

    /** Save rates and apply them to candidates that are still awaiting review. */
    public function saveSettings(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            foreach (self::DEFAULTS as $key => $default) {
                DbConfig::set("support_incentive.{$key}", max(0, (int) ($settings[$key] ?? $default)));
            }

            $this->refreshPendingCandidates();
        });
    }

    /** Apply current rates to candidates that are still awaiting Helpdesk review. */
    public function refreshPendingCandidates(): void
    {
        foreach (SupportIncentiveCategory::cases() as $category) {
            SupportIncentive::query()
                ->where('status', SupportIncentiveStatus::Pending->value)
                ->where('category', $category->value)
                ->update(['rate' => $this->forCategory($category)]);
        }
    }
}

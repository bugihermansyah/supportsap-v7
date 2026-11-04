<?php

namespace App\Filament\Widgets;

use App\Enums\LocationStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class LocationProductGroupStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Lokasi aktif & kontrak aktif per group product';

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 3,
        ];
    }

    protected function getStats(): array
    {
        $groups = [
            'manless' => 'Manless',
            'cass' => 'CASS',
            'other' => 'Other',
        ];

        $user = auth()->user();

        $query = DB::table('products')
            ->leftJoin('contracts', 'contracts.product_id', '=', 'products.id')
            ->leftJoin('locations', 'locations.id', '=', 'contracts.location_id')
            ->whereIn('products.group', array_keys($groups))
            ->select('products.group')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN locations.status = ? AND contracts.status = 1 THEN locations.id END) as active_locations_count',
                [LocationStatus::Active->value],
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN contracts.status = 1 THEN contracts.id END) as active_contracts_count',
            )
            ->groupBy('products.group');

        if ($user instanceof User && $user->hasTeamScopedSupportRole() && $user->team_id) {
            $query->where('locations.team_id', $user->team_id);
        }

        $counts = $query->get()->keyBy('group');

        return collect($groups)
            ->map(function (string $label, string $group) use ($counts): Stat {
                $count = $counts->get($group);

                return Stat::make($label.' — lokasi aktif', number_format((int) ($count->active_locations_count ?? 0), 0, ',', '.'))
                    ->description(number_format((int) ($count->active_contracts_count ?? 0), 0, ',', '.').' kontrak aktif')
                    ->descriptionIcon('heroicon-o-document-check');
            })
            ->all();
    }
}

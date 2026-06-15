<?php

namespace App\Filament\Widgets;

use App\Models\Reporting;
use Guava\Calendar\Filament\CalendarWidget as FilamentCalendarWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Guava\Calendar\ValueObjects\FetchInfo;
use Guava\Calendar\Enums\CalendarViewType;

class CalendarWidget extends FilamentCalendarWidget
{
    protected static bool $isDiscovered = false;

    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected function getEvents(FetchInfo $info): Collection | array | Builder
    {
        $query = Reporting::query()
            ->select(['id', 'status', 'date_visit', 'outstanding_id'])
            ->with(['users.team', 'outstanding.location'])
            ->whereNotNull('date_visit')
            ->whereBetween('date_visit', [
                $info->start->format('Y-m-d'),
                $info->end->format('Y-m-d')
            ]);

        $currentUser = auth()->user();
        if ($currentUser && $currentUser->hasRole(['head_support', 'support'])) {
            $teamId = $currentUser->team_id;
            $query->whereHas('users', function ($q) use ($teamId) {
                $q->where('users.team_id', $teamId);
            });
        }

        return $query->get()
            ->flatMap(function ($reporting) use ($currentUser) {
                $statusValue = $reporting->getRawOriginal('status') ?? $reporting->status?->value ?? '0';
                $statusMap = [
                    '1' => 'F',
                    '0' => 'P',
                    '2' => 'C',
                    '3' => 'T',
                    '4' => 'M',
                ];
                $status = $statusMap[$statusValue] ?? 'X';

                $locationName = $reporting->outstanding?->location?->name ?? 'No Location';

                $users = $reporting->users;
                if ($currentUser && $currentUser->hasRole(['head_support', 'support'])) {
                    $users = $users->where('team_id', $currentUser->team_id);
                }

                return $users->map(function ($user) use ($status, $locationName, $reporting) {
                    $backgroundColor = $user->team?->color ?? '#6b7280';
                    
                    return \Guava\Calendar\ValueObjects\CalendarEvent::make()
                        ->key($reporting->id . '-' . $user->id)
                        ->title($status . ' | ' . $user->firstname . ' | ' . $locationName)
                        ->start($reporting->date_visit)
                        ->end($reporting->date_visit)
                        ->allDay()
                        ->backgroundColor($backgroundColor);
                });
            });
    }
}

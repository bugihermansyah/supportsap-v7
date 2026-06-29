<?php

namespace App\Jobs;

use App\Models\Reporting;
use App\Models\ReportingUser;
use App\Models\UserProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalculateSupportTravelDistance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $reportingId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $reportingId)
    {
        $this->reportingId = $reportingId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reporting = Reporting::with(['users', 'outstanding.location'])->find($this->reportingId);

        if (!$reporting) {
            return;
        }

        $isRemote = strtolower($reporting->work) === 'remote';

        foreach ($reporting->users as $user) {
            // Find pivot record
            $reportingUser = ReportingUser::where('reporting_id', $reporting->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$reportingUser) {
                continue;
            }

            if ($isRemote) {
                $reportingUser->update([
                    'distance' => 0,
                    'duration' => 0,
                    'origin_name' => 'Remote',
                    'dest_name' => $reporting->location_title,
                ]);
                continue;
            }

            // Find previous reporting on the same day for this user
            $dateVisit = $reporting->date_visit;

            $previousReportingUser = ReportingUser::where('user_id', $user->id)
                ->whereHas('reporting', function ($query) use ($dateVisit) {
                    $query->where('date_visit', $dateVisit)
                          ->where('work', '!=', 'remote');
                })
                ->where('id', '<', $reportingUser->id)
                ->orderBy('id', 'desc')
                ->first();

            $originLat = null;
            $originLng = null;
            $originName = null;

            if ($previousReportingUser && $previousReportingUser->reporting) {
                // Origin is the destination of the previous reporting (Location)
                $prevLocation = $previousReportingUser->reporting->outstanding?->location;
                if ($prevLocation) {
                    $originLat = $prevLocation->lat;
                    $originLng = $prevLocation->lng;
                    $originName = $prevLocation->name;
                }
            } else {
                // Origin is the user's base/home
                $profile = UserProfile::where('user_id', $user->id)->first();
                if ($profile && $profile->lat && $profile->lng) {
                    $originLat = $profile->lat;
                    $originLng = $profile->lng;
                    $originName = 'Base';
                }
            }

            $destLocation = $reporting->outstanding?->location;
            $destLat = $destLocation?->lat;
            $destLng = $destLocation?->lng;
            $destName = $destLocation?->name;

            // If coordinates are missing, set distance to 0
            if (!$originLat || !$originLng || !$destLat || !$destLng) {
                $reportingUser->update([
                    'distance' => 0,
                    'duration' => 0,
                    'origin_name' => $originName ?? 'Unknown',
                    'dest_name' => $destName ?? 'Unknown',
                    'origin_lat' => $originLat,
                    'origin_lng' => $originLng,
                    'dest_lat' => $destLat,
                    'dest_lng' => $destLng,
                ]);
                continue;
            }

            // If origin is exactly destination, distance is 0
            if ((string)$originLat === (string)$destLat && (string)$originLng === (string)$destLng) {
                $reportingUser->update([
                    'distance' => 0,
                    'duration' => 0,
                    'origin_name' => $originName,
                    'dest_name' => $destName,
                    'origin_lat' => $originLat,
                    'origin_lng' => $originLng,
                    'dest_lat' => $destLat,
                    'dest_lng' => $destLng,
                ]);
                continue;
            }

            // Call OSRM
            $distance = 0;
            $duration = 0;

            try {
                // OSRM format: lng,lat;lng,lat
                $url = "https://router.project-osrm.org/route/v1/driving/{$originLng},{$originLat};{$destLng},{$destLat}?overview=false";
                $response = Http::timeout(10)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['routes'][0])) {
                        $route = $data['routes'][0];
                        $distance = ($route['distance'] ?? 0) / 1000; // Convert meters to KM
                        $duration = $route['duration'] ?? 0; // In seconds
                    }
                } else {
                    Log::error("OSRM API Error: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("OSRM API Exception: " . $e->getMessage());
            }

            $reportingUser->update([
                'distance' => $distance,
                'duration' => $duration,
                'origin_name' => $originName,
                'dest_name' => $destName,
                'origin_lat' => $originLat,
                'origin_lng' => $originLng,
                'dest_lat' => $destLat,
                'dest_lng' => $destLng,
            ]);
        }
    }
}

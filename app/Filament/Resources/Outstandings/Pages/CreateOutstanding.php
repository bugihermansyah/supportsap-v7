<?php

namespace App\Filament\Resources\Outstandings\Pages;

use App\Filament\Resources\Outstandings\OutstandingResource;
use App\Models\Location;
use App\Models\Outstanding;
use App\Models\Reporting;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOutstanding extends CreateRecord
{
    protected static string $resource = OutstandingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lastOutstanding = null;
        $isTask = $this->data['task'] ?? false;

        if ($isTask) {
            foreach (($data['problems'] ?? []) as $problem) {
                // Create new outstanding  
                $dataCreate = [
                    'number' => 'SP-' . Carbon::now()->format('ym') . '' . (random_int(100000, 999999)),
                    'location_id' => $data['location_id'],
                    'product_id' => $data['product_id'] ?? null,
                    'team_id' => Location::find($data['location_id'])->team_id,
                    'reporter' => $data['reporter'] ?? 'client',
                    'reporter_name' => $data['reporter_name'] ?? '',
                    'is_implement' => $data['is_implement'] ?? false,
                    'is_oncall' => $data['is_oncall'] ?? false,
                    'title' => is_array($problem) ? ($problem['title'] ?? '') : $problem,
                    'date_in' => $data['date_in'] ?? null,
                    'date_visit' => $data['date_visit'],
                    'user_id' => auth()->id(),
                ];

                $lpm = 0;

                $reporter = $data['reporter'] ?? 'client';
                if ($reporter === 'client') {
                    $dateInRaw = $data['date_in'] ?? now()->toDateString();
                    $dateIn = Carbon::parse($dateInRaw)->toDateString();

                    $exists = Outstanding::where('location_id', $data['location_id'])
                        ->whereDate('date_in', $dateIn)
                        ->where('lpm', 1)
                        ->exists();

                    $lpm = $exists ? 0 : 1;
                }

                $dataCreate['lpm'] = $lpm;

                $outstanding = Outstanding::create($dataCreate);
                $lastOutstanding = $outstanding;

                // Fetch email_to and email_cc from the pivot table
                $location = Location::find($data['location_id']);
                $emailTo = $location ? $location->customers()
                    ->wherePivot('is_to', true)
                    ->pluck('email')
                    ->toArray() : [];

                $emailCc = $location ? $location->customers()
                    ->wherePivot('is_to', false)
                    ->pluck('email')
                    ->toArray() : [];

                // Create new reporting
                $reporting = Reporting::create([
                    'outstanding_id' => $outstanding->id,
                    'date_visit' => $data['date_visit'],
                    'status' => null,
                    'email_to' => $emailTo,
                    'email_cc' => $emailCc,
                ]);
                // Attach multiple users to the reporting and send email
                if (isset($data['user_id']) && is_array($data['user_id'])) {
                    $reporting->users()->attach($data['user_id']);

                    // Get necessary data for the email
                    $companyAlias = $location->company->alias ?? 'SAP';
                    $locationName = $location->name;
                    $title = $outstanding->title;
                    $dateVisit = $data['date_visit'];
                    $reporter = $outstanding->reporter ?? '-';
                    $reporterName = $outstanding->reporter_name ?? '-';

                    // Collect all support emails
                    $supportEmails = User::whereIn('id', $data['user_id'])
                        ->pluck('email')
                        ->toArray();

                    // Dispatch a single email job with multiple recipients
                    if (!empty($supportEmails)) {
                        if (class_exists(\App\Jobs\ScheduleMailJob::class)) {
                            \App\Jobs\ScheduleMailJob::dispatch(
                                $supportEmails,
                                $dateVisit,
                                $companyAlias,
                                $locationName,
                                $title,
                                $reporter,
                                $reporterName
                            )->onQueue('scheduleEmails');
                        }
                    }

                    $assignedUsers = User::whereIn('id', $data['user_id'])->get();
                    foreach ($assignedUsers as $assignedUser) {
                        Notification::make()
                            ->title('New schedule')
                            ->body("New schedule at {$locationName} for problem: {$title} on {$dateVisit}")
                            ->info()
                            ->sendToDatabase($assignedUser);
                    }
                }
            }
        } else {
            // Create new reporting
            foreach (($data['outstanding_id'] ?? []) as $check_id) {
                // Fetch the outstanding record
                $outstanding = Outstanding::find($check_id);
                $lastOutstanding = $outstanding;

                // Fetch email_to and email_cc from the pivot table
                $location = $outstanding->location;
                $emailTo = $location ? $location->customers()
                    ->wherePivot('is_to', true)
                    ->pluck('email')
                    ->toArray() : [];

                $emailCc = $location ? $location->customers()
                    ->wherePivot('is_to', false)
                    ->pluck('email')
                    ->toArray() : [];

                $reporting = Reporting::create([
                    'outstanding_id' => $check_id,
                    'date_visit' => $data['date_visit'],
                    'status' => null,
                    'email_to' => $emailTo,
                    'email_cc' => $emailCc,
                ]);
                // Attach multiple users to the reporting and send email
                if (isset($data['user_id']) && is_array($data['user_id'])) {
                    $reporting->users()->attach($data['user_id']);

                    // Get necessary data for the email
                    $companyAlias = $location->company->alias ?? 'SAP';
                    $locationName = $location->name;
                    $title = $outstanding->title;
                    $dateVisit = $data['date_visit'];
                    $reporter = $outstanding->reporter ?? '-';
                    $reporterName = $outstanding->reporter_name ?? '-';

                    // Collect all support emails
                    $supportEmails = User::whereIn('id', $data['user_id'])
                        ->pluck('email')
                        ->toArray();

                    // Dispatch a single email job with multiple recipients
                    if (!empty($supportEmails)) {
                        if (class_exists(\App\Jobs\ScheduleMailJob::class)) {
                            \App\Jobs\ScheduleMailJob::dispatch(
                                $supportEmails,
                                $dateVisit,
                                $companyAlias,
                                $locationName,
                                $title,
                                $reporter,
                                $reporterName
                            )->onQueue('scheduleEmails');
                        }
                    }

                    $assignedUsers = User::whereIn('id', $data['user_id'])->get();
                    foreach ($assignedUsers as $assignedUser) {
                        Notification::make()
                            ->title('New schedule')
                            ->body("New schedule at {$locationName} for problem: {$title} on {$dateVisit}")
                            ->info()
                            ->sendToDatabase($assignedUser);
                    }
                }
            }
        }

        return $lastOutstanding ?? new Outstanding();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

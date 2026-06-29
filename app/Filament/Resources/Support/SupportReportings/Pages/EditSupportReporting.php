<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

use App\Enums\ReportStatus;
use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
use App\Mail\SupportReportingMail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Inerba\DbConfig\DbConfig;

class EditSupportReporting extends EditRecord
{
    protected static string $resource = SupportReportingResource::class;

    protected bool $shouldSendEmail = false;

    public function getTitle(): string 
    {
        return $this->record->location_title; 
    }

    public function getHeading(): string
    {
        return $this->record->location_title;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['is_type_problem'])) {
            $this->record->outstanding?->update([
                'is_type_problem' => $data['is_type_problem'],
            ]);

            unset($data['is_type_problem']);
        }

        // Jika lokasi HO, paksa status menjadi Finish
        if ($this->record->outstanding?->location?->is_ho) {
            $data['status'] = ReportStatus::Finish->value;
        }
    
        // Set end_work on the first save only (when it's still null)
        if (empty($this->record->end_work)) {
            $data['end_work'] = now();
            $this->shouldSendEmail = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Only send email if end_work was just set (first save)
        if ($this->shouldSendEmail) {
            $this->sendReportingEmail($this->record);
        }

        $reporting = $this->record;
        $outstanding = $reporting->outstanding;

        if ($outstanding) {
            $statusValue = $reporting->status->value ?? $reporting->status;
            $updateData = [];

            if ($statusValue === '0') { // Pending
                $updateData['date_finish'] = null;
                $updateData['status'] = '0'; // OutstandingStatus::Open
            } elseif ($statusValue === '1') { // Finish
                $updateData['date_finish'] = $reporting->date_visit;
                $updateData['status'] = '1'; // OutstandingStatus::Close
            } elseif ($statusValue === '2') { // Pending Client
                $updateData['date_finish'] = $reporting->date_visit;
                $updateData['status'] = '0'; // OutstandingStatus::Open
            } elseif ($statusValue === '3') { // Temporary
                $updateData['date_finish'] = $reporting->date_visit;
                $updateData['status'] = '0'; // OutstandingStatus::Open
                $updateData['date_temporary'] = $reporting->date_visit;
            } elseif ($statusValue === '4') { // Monitoring
                $updateData['date_finish'] = $reporting->date_visit;
                $updateData['status'] = '0'; // OutstandingStatus::Open
            }

            if (!empty($updateData)) {
                $outstanding->update($updateData);
            }
        }

        \App\Jobs\CalculateSupportTravelDistance::dispatch($this->record->id);
    }

    /**
     * Send email notification based on the team of the location.
     */
    protected function sendReportingEmail(Model $reporting): void
    {
        // Eager load needed relationships
        $reporting->load(['outstanding.location.team', 'outstanding.location.company', 'users']);

        $teamName = $reporting->outstanding?->location?->team?->name;

        if (!$teamName) {
            return;
        }

        // Get TO and CC from Team table
        $team = $reporting->outstanding?->location?->team;
        $toEmail = $team?->email_to;
        $ccEmail = $team?->email_cc;

        if (empty($toEmail)) {
            return;
        }

        $mail = Mail::to($toEmail);

        if (!empty($ccEmail)) {
            $mail->cc($ccEmail);
        }

        $mail->queue(new SupportReportingMail($reporting));
    }



    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Send');
    }

    protected function getRedirectUrl(): string
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        if ($user && $user->hasRole('support') && !$user->hasRole(['head_support'])) {
            return '/';
        }

        return '/schedule-dashboard';
    }

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }
}

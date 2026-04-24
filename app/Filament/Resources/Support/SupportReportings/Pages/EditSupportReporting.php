<?php

namespace App\Filament\Resources\Support\SupportReportings\Pages;

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

        // Map team name to db_config key
        $teamKey = $this->resolveTeamKey($teamName);

        if (!$teamKey) {
            return;
        }

        // Get TO and CC from db_config
        $toEmail = DbConfig::get("mail.{$teamKey['to']}");
        $ccEmail = DbConfig::get("mail.{$teamKey['cc']}");

        if (empty($toEmail)) {
            return;
        }

        $mail = Mail::to($toEmail);

        if (!empty($ccEmail)) {
            $mail->cc($ccEmail);
        }

        $mail->queue(new SupportReportingMail($reporting));
    }

    /**
     * Resolve team name to the corresponding db_config keys for TO and CC.
     */
    protected function resolveTeamKey(string $teamName): ?array
    {
        return match (strtolower($teamName)) {
            'barat'      => ['to' => 'to_barat', 'cc' => 'cc_barat'],
            'timur'      => ['to' => 'to_timur', 'cc' => 'cc_timur'],
            'pusat'      => ['to' => 'to_pusat', 'cc' => 'cc_pusat'],
            'cass barat' => ['to' => 'to_cass_barat', 'cc' => 'cc_cass_barat'],
            'luar kota'  => ['to' => 'to_luar_kota', 'cc' => 'cc_luar_kota'],
            default      => null,
        };
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Send');
    }

    protected function getRedirectUrl(): string
    {
        return '/';
    }

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Alasan sebuah laporan berstatus Pending SAP / Pending Client.
 * Wajib diisi PIC saat memilih kedua status tersebut.
 */
enum PendingReason: string implements HasLabel
{
    case NoClientPermission = 'no_client_permission';
    case WrongUnit = 'wrong_unit';
    case SpecialTools = 'special_tools';
    case NeedsMorePeople = 'needs_more_people';
    case ClientDeviceIssue = 'client_device_issue';
    case BrokenTools = 'broken_tools';
    case VehicleBreakdown = 'vehicle_breakdown';
    case NeedsCoordination = 'needs_coordination';
    case Weather = 'weather';
    case ReplacementUnitRequest = 'replacement_unit_request';

    public function getLabel(): string
    {
        return match ($this) {
            self::NoClientPermission => 'Tidak ada izin dari client',
            self::WrongUnit => 'Salah unit',
            self::SpecialTools => 'Butuh tool khusus',
            self::NeedsMorePeople => 'Pekerjaan butuh lebih dari 1 orang',
            self::ClientDeviceIssue => 'Gangguan dari perangkat client',
            self::BrokenTools => 'Kerusakan tool',
            self::VehicleBreakdown => 'Kendaraan trouble',
            self::NeedsCoordination => 'Koordinasi internal/vendor',
            self::Weather => 'Kendala cuaca',
            self::ReplacementUnitRequest => 'Req. unit pengganti',
        };
    }

    /** Status yang mewajibkan alasan pending. */
    public static function requiredForStatuses(): array
    {
        return [ReportStatus::Pending, ReportStatus::PendingClient];
    }

    /** @return array<int, string> nilai status ('0','2') untuk dipakai di closure form */
    public static function requiredForStatusValues(): array
    {
        return array_map(fn (ReportStatus $status) => $status->value, self::requiredForStatuses());
    }

    /** Apakah status ini (enum atau nilai mentah) wajib menyertakan alasan? */
    public static function isRequiredFor(mixed $status): bool
    {
        $value = $status instanceof ReportStatus ? $status->value : (is_scalar($status) ? (string) $status : null);

        return $value !== null && in_array($value, self::requiredForStatusValues(), true);
    }
}

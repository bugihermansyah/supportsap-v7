<x-mail::message>
<div style="border-left: 4px solid #3b82f6; padding-left: 12px; margin-bottom: 16px;">
<strong style="font-size: 15px;">Notifikasi Jadwal</strong>
</div>

<p>Halo, {{ filled($supportNames) ? implode(', ', $supportNames) : 'Tim Support' }}.</p>
<p>Jadwal kunjungan baru telah dibuat. Berikut detailnya:</p>

<table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 1.6; margin-bottom: 16px;">
<tr>
<td style="padding: 2px 0; color: #6b7280; width: 140px; vertical-align: top;">Perusahaan:</td>
<td style="padding: 2px 0;"><strong>{{ $companyAlias }}</strong></td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Lokasi:</td>
<td style="padding: 2px 0;">{{ $locationName }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Masalah:</td>
<td style="padding: 2px 0;">{{ $title }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Pelapor:</td>
<td style="padding: 2px 0;">{{ ucfirst($reporter) }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Nama Pelapor:</td>
<td style="padding: 2px 0;">{{ $reporterName }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Dibuat oleh:</td>
<td style="padding: 2px 0;">{{ $createdBy }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Tanggal Kunjungan:</td>
<td style="padding: 2px 0;"><strong>{{ \Carbon\Carbon::parse($dateVisit)->locale('id')->translatedFormat('l, d M Y') }}</strong></td>
</tr>
</table>

<x-mail::button :url="config('app.url')">
Lihat Sistem
</x-mail::button>

Terima kasih,<br>
Support SAP
</x-mail::message>

<x-mail::message>
<style>
.content-html ol, .content-html ul {
    margin: 4px 0 4px 0;
    padding-left: 20px;
}
.content-html li {
    margin: 0;
    padding: 1px 0;
    line-height: 1.5;
    font-size: 14px;
}
.content-html p {
    margin: 2px 0;
    font-size: 14px;
}
</style>

<div style="border-left: 4px solid #3b82f6; padding-left: 12px; margin-bottom: 16px;">
<strong style="font-size: 15px;">{{ $outstanding?->number ?? '-' }}</strong>
</div>

<table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 1.6;">
<tr>
<td style="padding: 2px 0; color: #6b7280; width: 140px; vertical-align: top;">Location:</td>
<td style="padding: 2px 0;">{{ $location?->company?->alias ?? '-' }} - {{ $location?->name ?? '-' }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; width: 140px; vertical-align: top;">Info Date:</td>
<td style="padding: 2px 0;">{{ $outstanding?->date_in ? \Carbon\Carbon::parse($outstanding->date_in)->translatedFormat('d M Y') : '-' }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Visit Date:</td>
<td style="padding: 2px 0;">{{ $reporting->date_visit ? \Carbon\Carbon::parse($reporting->date_visit)->translatedFormat('d M Y') : '-' }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Support:</td>
<td style="padding: 2px 0;">{{ $users->pluck('name')->join(', ') ?: '-' }} / {{ ucfirst($reporting->work ?? '-') }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Reported by:</td>
<td style="padding: 2px 0;">Bpk/Ibu {{ $outstanding?->reporter_name ?? '-' }}</td>
</tr>
</table>

<div style="border-top: 1px solid #e5e7eb; margin: 12px 0;"></div>

<table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 1.6;">
<tr>
<td style="padding: 2px 0; color: #6b7280; width: 140px; vertical-align: top;">Problem:</td>
<td style="padding: 2px 0;">{{ $outstanding?->title ?? '-' }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Reason:</td>
<td style="padding: 2px 0;">{{ $reporting->cause ?? '-' }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Action:</td>
<td style="padding: 2px 0;"><div class="content-html">{!! $reporting->action ?? '-' !!}</div></td>
</tr>
@if($reporting->note)
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Note:</td>
<td style="padding: 2px 0;"><div class="content-html">{!! $reporting->note !!}</div></td>
</tr>
@endif
</table>

<div style="border-top: 1px solid #e5e7eb; margin: 12px 0;"></div>

<table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 1.6;">
<tr>
<td style="padding: 2px 0; color: #6b7280; width: 140px; vertical-align: top;">Status:</td>
<td style="padding: 2px 0;"><strong>{{ $reporting->status?->getLabel() ?? '-' }}</strong></td>
</tr>
@if($reporting->revisit)
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Revisit:</td>
<td style="padding: 2px 0;">{{ \Carbon\Carbon::parse($reporting->revisit)->translatedFormat('d M Y') }}</td>
</tr>
@endif
</table>
</x-mail::message>

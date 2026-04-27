<x-mail::message>
<div style="border-left: 4px solid #3b82f6; padding-left: 12px; margin-bottom: 16px;">
<strong style="font-size: 15px;">Schedule Notification</strong>
</div>

<p>Hello,</p>
<p>You have been scheduled for a visit. Here are the details:</p>

<table style="width: 100%; border-collapse: collapse; font-size: 14px; line-height: 1.6; margin-bottom: 16px;">
<tr>
<td style="padding: 2px 0; color: #6b7280; width: 140px; vertical-align: top;">Company:</td>
<td style="padding: 2px 0;"><strong>{{ $companyAlias }}</strong></td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Location:</td>
<td style="padding: 2px 0;">{{ $locationName }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Problem/Title:</td>
<td style="padding: 2px 0;">{{ $title }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Reporter:</td>
<td style="padding: 2px 0;">{{ ucfirst($reporter) }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Reporter Name:</td>
<td style="padding: 2px 0;">{{ $reporterName }}</td>
</tr>
<tr>
<td style="padding: 2px 0; color: #6b7280; vertical-align: top;">Visit Date:</td>
<td style="padding: 2px 0;"><strong>{{ \Carbon\Carbon::parse($dateVisit)->translatedFormat('l, d M Y') }}</strong></td>
</tr>
</table>

<x-mail::button :url="config('app.url')">
View System
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

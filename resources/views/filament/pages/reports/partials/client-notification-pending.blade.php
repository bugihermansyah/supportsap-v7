@php
    use App\Filament\Resources\Support\SupportReportings\SupportReportingResource;
@endphp

<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ $rows->count() }} laporan pada tiket pelapor <strong>client</strong> yang emailnya belum dikirim
        @if ($rows->count() === 100)
            (dibatasi 100 terlama)
        @endif
        .
    </p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="py-2">Tanggal kunjungan</th>
                    <th class="py-2">Umur</th>
                    <th class="py-2">Lokasi</th>
                    <th class="py-2">Outstanding</th>
                    <th class="py-2">Support</th>
                    <th class="py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($rows as $row)
                    @php
                        $visit = \Illuminate\Support\Carbon::parse($row->date_visit)->startOfDay();
                        $age = (int) $visit->diffInDays(now()->startOfDay());
                    @endphp
                    <tr>
                        <td class="py-2 whitespace-nowrap">{{ $visit->translatedFormat('d M Y') }}</td>
                        <td class="py-2">
                            <x-filament::badge :color="$age > 7 ? 'danger' : ($age > 1 ? 'warning' : 'gray')" size="sm">
                                {{ $age === 0 ? 'Hari ini' : $age.' hari' }}
                            </x-filament::badge>
                        </td>
                        <td class="py-2">{{ $row->outstanding?->location?->name ?? '-' }}</td>
                        <td class="py-2">{{ $row->outstanding?->title ?? '-' }}</td>
                        <td class="py-2">{{ $row->users->pluck('name')->join(', ') ?: '-' }}</td>
                        <td class="py-2 text-right">
                            <x-filament::link
                                :href="SupportReportingResource::getUrl('send-email', ['record' => $row])"
                                target="_blank"
                                size="sm">
                                Kirim email
                            </x-filament::link>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">
                            Tidak ada tunggakan pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

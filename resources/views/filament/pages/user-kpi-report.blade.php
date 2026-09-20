<x-filament-panels::page>
    <form wire:submit="generateReport">
        {{ $this->form }}

        <div class="mt-4 text-right">
            <x-filament::button type="submit">
                Generate Raport
            </x-filament::button>
        </div>
    </form>

    @if($selectedUser)
        <div class="mt-8 bg-white dark:bg-gray-800 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <!-- Header Raport -->
            <div class="border-b-4 border-primary-500 pb-6 mb-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white uppercase tracking-wider">Raport Kinerja Support</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Periode: {{ \Carbon\Carbon::parse($month)->format('F Y') }}</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ $selectedUser->name }}</h2>
                        <p class="text-gray-500 dark:text-gray-400">{{ $selectedUser->email }}</p>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg border border-gray-100 dark:border-gray-700 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Total Pekerjaan</p>
                    <p class="text-4xl font-bold text-gray-800 dark:text-gray-200">{{ $totalCases }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg border border-gray-100 dark:border-gray-700 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Rata-rata Nilai</p>
                    <p class="text-4xl font-bold text-gray-800 dark:text-gray-200">{{ $averageScore }}</p>
                </div>
                <div class="bg-primary-50 dark:bg-primary-900/30 p-6 rounded-lg border border-primary-100 dark:border-primary-800 text-center">
                    <p class="text-sm text-primary-600 dark:text-primary-400 uppercase tracking-widest mb-1">Grade Akhir</p>
                    <p class="text-5xl font-extrabold text-primary-600 dark:text-primary-400">{{ $grade }}</p>
                </div>
            </div>

            <!-- Rincian Nilai -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">Rincian Pekerjaan</h3>

                @if(count($reportings) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-300 text-sm uppercase tracking-wider">
                                    <th class="p-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Tanggal</th>
                                    <th class="p-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Klien / Lokasi</th>
                                    <th class="p-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Tipe Pekerjaan</th>
                                    <th class="p-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Durasi</th>
                                    <th class="p-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-center">Nilai</th>
                                    <th class="p-3 border-b border-gray-200 dark:border-gray-700 font-semibold">Catatan Evaluasi</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-300 text-sm">
                                @foreach($reportings as $report)
                                    @php
                                        $duration = '-';
                                        if($report->start_work && $report->end_work) {
                                            $start = \Carbon\Carbon::parse($report->start_work);
                                            $end = \Carbon\Carbon::parse($report->end_work);
                                            $duration = $start->diffForHumans($end, ['parts' => 2, 'syntax' => \Carbon\Carbon::DIFF_ABSOLUTE]);
                                        }
                                        $locationName = $report->outstanding ? ($report->outstanding->location ? $report->outstanding->location->name : '-') : '-';
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <td class="p-3">{{ \Carbon\Carbon::parse($report->date_visit)->format('d M Y') }}</td>
                                        <td class="p-3 font-medium">{{ $locationName }}</td>
                                        <td class="p-3 capitalize">{{ $report->work }}</td>
                                        <td class="p-3">{{ $duration }}</td>
                                        <td class="p-3 text-center">
                                            @if($report->score !== null)
                                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ $report->score >= 85 ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' :
                                                    ($report->score >= 70 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300' :
                                                    'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300') }}">
                                                    {{ $report->score }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 italic">N/A</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-xs">{{ $report->evaluation_note ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="bg-gray-50 dark:bg-gray-900 p-8 rounded-lg text-center border border-dashed border-gray-300 dark:border-gray-700">
                        <p class="text-gray-500 dark:text-gray-400">Belum ada laporan pekerjaan di bulan ini.</p>
                    </div>
                @endif
            </div>

            <!-- Catatan Tambahan -->
            <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 rounded-r-lg">
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    <strong>Catatan Sistem:</strong> Penilaian ini didasarkan pada kualitas penyelesaian masalah (Outstanding) dan ketepatan waktu pelaporan. Nilai di atas 85 mendapatkan Grade A. Laporan H+1 atau ketidaksesuaian prosedur dapat mengurangi nilai secara signifikan.
                </p>
            </div>
        </div>
    @endif
</x-filament-panels::page>

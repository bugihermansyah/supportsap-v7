@php
    $sessions = $this->sessionOptions();
    $session = $this->currentSession();
    $me = auth()->id();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Pilih tampilan</x-slot>

        <select wire:model.live="sessionId"
            class="block w-full max-w-md rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
            <option value="">Rekap semua sesi</option>
            @foreach ($sessions as $option)
                <option value="{{ $option->id }}">
                    {{ $option->title }} ({{ $option->starts_at->translatedFormat('d M Y') }})
                </option>
            @endforeach
        </select>
    </x-filament::section>

    @if ($session && ! $this->resultsVisible())
        {{-- Sesi masih berjalan: peringkat belum boleh dibuka untuk peserta. --}}
        <x-filament::section>
            <x-slot name="heading">{{ $session->title }}</x-slot>
            <x-slot name="description">
                {{ $session->starts_at->translatedFormat('d M Y H:i') }} &ndash; {{ $session->ends_at->translatedFormat('d M Y H:i') }}
            </x-slot>

            <div class="flex flex-col items-center gap-3 py-8 text-center">
                <x-filament::icon icon="heroicon-o-lock-closed" class="h-10 w-10 text-gray-400 dark:text-gray-500" />

                <p class="text-base font-semibold text-gray-950 dark:text-white">Leaderboard belum dibuka</p>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Peringkat sesi ini bisa dilihat setelah sesi ditutup pada
                    {{ $session->ends_at->translatedFormat('d M Y H:i') }}.
                </p>
            </div>
        </x-filament::section>
    @elseif ($session)
        @php $ranking = $this->sessionRanking(); @endphp

        <x-filament::section>
            <x-slot name="heading">{{ $session->title }}</x-slot>
            <x-slot name="description">
                {{ $session->starts_at->translatedFormat('d M Y H:i') }} &ndash; {{ $session->ends_at->translatedFormat('d M Y H:i') }}
                &middot; {{ $ranking->count() }} peserta selesai
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="w-12 py-2">#</th>
                            <th class="py-2">Peserta</th>
                            <th class="py-2 text-center">Benar</th>
                            <th class="py-2 text-center">Nilai</th>
                            <th class="py-2 text-center">Durasi</th>
                            <th class="py-2 text-right">Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($ranking as $i => $row)
                            <tr class="{{ $row->user_id === $me ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                                <td class="py-2 font-semibold">
                                    @if ($i === 0) 🥇 @elseif ($i === 1) 🥈 @elseif ($i === 2) 🥉 @else {{ $i + 1 }} @endif
                                </td>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $row->user?->name ?? '-' }}</td>
                                <td class="py-2 text-center">{{ $row->correct_answers }}/{{ $row->total_questions }}</td>
                                <td class="py-2 text-center">
                                    <x-filament::badge color="{{ $row->scorePercentage() >= 70 ? 'success' : ($row->scorePercentage() >= 50 ? 'warning' : 'danger') }}">
                                        {{ $row->scorePercentage() }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-2 text-center font-mono">{{ \App\Models\QuizAttempt::formatDuration($row->duration_seconds) }}</td>
                                <td class="py-2 text-right text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row->finished_at?->translatedFormat('d M Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada peserta yang menyelesaikan sesi ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @else
        @php $overall = $this->overallRanking(); @endphp

        <x-filament::section>
            <x-slot name="heading">Rekap semua sesi</x-slot>
            <x-slot name="description">Hanya sesi yang sudah ditutup. Diurutkan dari total jawaban benar terbanyak, lalu total durasi tercepat.</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="w-12 py-2">#</th>
                            <th class="py-2">Peserta</th>
                            <th class="py-2 text-center">Sesi</th>
                            <th class="py-2 text-center">Benar</th>
                            <th class="py-2 text-center">Akurasi</th>
                            <th class="py-2 text-center">Total durasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($overall as $i => $row)
                            @php
                                $accuracy = $row->total_questions_sum > 0
                                    ? (int) round($row->total_correct / $row->total_questions_sum * 100)
                                    : 0;
                            @endphp
                            <tr class="{{ $row->user_id === $me ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                                <td class="py-2 font-semibold">
                                    @if ($i === 0) 🥇 @elseif ($i === 1) 🥈 @elseif ($i === 2) 🥉 @else {{ $i + 1 }} @endif
                                </td>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $row->user?->name ?? '-' }}</td>
                                <td class="py-2 text-center">{{ $row->sessions_count }}</td>
                                <td class="py-2 text-center">{{ $row->total_correct }}/{{ $row->total_questions_sum }}</td>
                                <td class="py-2 text-center">
                                    <x-filament::badge color="{{ $accuracy >= 70 ? 'success' : ($accuracy >= 50 ? 'warning' : 'danger') }}">
                                        {{ $accuracy }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-2 text-center font-mono">{{ \App\Models\QuizAttempt::formatDuration((int) $row->total_duration) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada data pengerjaan quiz.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

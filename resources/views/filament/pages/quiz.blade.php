@php
    $attempt = $this->attempt();
@endphp

<x-filament-panels::page>
    {{-- ============================================================ --}}
    {{-- 1. Daftar sesi --}}
    {{-- ============================================================ --}}
    @if (! $attempt)
        <x-filament::section>
            <x-slot name="heading">Quiz yang sedang dibuka</x-slot>
            <x-slot name="description">Setiap sesi hanya boleh dikerjakan satu kali. Urutan soal diacak untuk tiap peserta, dan hasilnya baru dibuka setelah sesi ditutup.</x-slot>

            @php $sessions = $this->sessions(); @endphp

            @forelse ($sessions as $session)
                @php
                    $myAttempt = $session->attempts->first();
                @endphp
                <div class="flex flex-col gap-3 border-b border-gray-200 py-4 last:border-0 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $session->title }}</span>
                            <x-filament::badge color="gray" size="sm">{{ $session->questions_count }} soal</x-filament::badge>
                        </div>
                        @if ($session->description)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $session->description }}</p>
                        @endif
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Tersedia {{ $session->starts_at->translatedFormat('d M Y H:i') }}
                            &ndash; {{ $session->ends_at->translatedFormat('d M Y H:i') }}
                        </p>
                    </div>

                    <div class="shrink-0">
                        @if ($myAttempt && $myAttempt->finished_at)
                            @if ($session->resultsVisible())
                                <div class="flex items-center gap-2">
                                    <x-filament::badge color="success">
                                        Nilai {{ $myAttempt->correct_answers }}/{{ $myAttempt->total_questions }}
                                    </x-filament::badge>
                                    <x-filament::button size="sm" color="gray" wire:click="openAttempt('{{ $myAttempt->id }}')">
                                        Lihat hasil
                                    </x-filament::button>
                                </div>
                            @else
                                {{-- Sesi masih berjalan: peserta belum boleh tahu nilainya. --}}
                                <div class="flex items-center gap-2">
                                    <x-filament::badge color="gray" icon="heroicon-m-check-circle">
                                        Sudah dikerjakan
                                    </x-filament::badge>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Hasil dibuka {{ $session->ends_at->translatedFormat('d M Y H:i') }}
                                    </span>
                                </div>
                            @endif
                        @elseif ($myAttempt)
                            <x-filament::button size="sm" color="warning" wire:click="openAttempt('{{ $myAttempt->id }}')">
                                Lanjutkan
                            </x-filament::button>
                        @else
                            {{ ($this->startAction)(['session' => $session->id]) }}
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada quiz yang dibuka saat ini.
                </p>
            @endforelse
        </x-filament::section>

        @php $history = $this->history(); @endphp

        @if ($history->isNotEmpty())
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Riwayat quiz saya</x-slot>

                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($history as $row)
                        @php $released = (bool) $row->session?->resultsVisible(); @endphp
                        <div class="flex items-center justify-between gap-3 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $row->session?->title ?? 'Sesi terhapus' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Selesai {{ $row->finished_at->translatedFormat('d M Y H:i') }}
                                    @if ($released)
                                        &middot; durasi {{ \App\Models\QuizAttempt::formatDuration($row->duration_seconds) }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($released)
                                    <x-filament::badge color="{{ $row->scorePercentage() >= 70 ? 'success' : ($row->scorePercentage() >= 50 ? 'warning' : 'danger') }}">
                                        {{ $row->correct_answers }}/{{ $row->total_questions }}
                                    </x-filament::badge>
                                    <x-filament::button size="xs" color="gray" wire:click="openAttempt('{{ $row->id }}')">
                                        Detail
                                    </x-filament::button>
                                @else
                                    <x-filament::badge color="gray" icon="heroicon-m-lock-closed">
                                        Menunggu sesi ditutup
                                    </x-filament::badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

    {{-- ============================================================ --}}
    {{-- 2. Hasil --}}
    {{-- ============================================================ --}}
    @elseif ($attempt->isFinished() && ! $this->resultsVisible())
        {{-- Sesi masih berjalan: nilai dan pembahasan belum boleh dibuka. --}}
        <x-filament::section>
            <x-slot name="heading">{{ $attempt->session?->title }}</x-slot>
            <x-slot name="description">Jawaban kamu sudah terkunci.</x-slot>

            <div class="flex flex-col items-center gap-3 py-8 text-center">
                <x-filament::icon icon="heroicon-o-lock-closed" class="h-10 w-10 text-gray-400 dark:text-gray-500" />

                <p class="text-base font-semibold text-gray-950 dark:text-white">Hasil belum bisa dilihat</p>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Nilai, peringkat dan pembahasan dibuka setelah sesi ini ditutup
                    @if ($attempt->session)
                        pada {{ $attempt->session->ends_at->translatedFormat('d M Y H:i') }}.
                    @endif
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Selesai {{ $attempt->finished_at?->translatedFormat('d M Y H:i') }}
                </p>

                <x-filament::button color="gray" wire:click="backToList">Kembali</x-filament::button>
            </div>
        </x-filament::section>

    @elseif ($attempt->isFinished())
        <x-filament::section>
            <x-slot name="heading">{{ $attempt->session?->title }}</x-slot>
            <x-slot name="description">Hasil pengerjaan kamu.</x-slot>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Jawaban benar</p>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $attempt->correct_answers }}/{{ $attempt->total_questions }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Nilai</p>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $attempt->scorePercentage() }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Durasi</p>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ \App\Models\QuizAttempt::formatDuration($attempt->duration_seconds) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Selesai</p>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $attempt->finished_at?->translatedFormat('d M Y H:i') }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-2">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Rekap per kategori produk</p>
                @foreach ($this->categoryRecap() as $recap)
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                        <span class="text-gray-700 dark:text-gray-300">{{ $recap['category'] }}</span>
                        <x-filament::badge color="{{ $recap['correct'] === $recap['total'] ? 'success' : ($recap['correct'] === 0 ? 'danger' : 'warning') }}">
                            {{ $recap['correct'] }}/{{ $recap['total'] }} benar
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <x-filament::button color="gray" wire:click="backToList">Kembali</x-filament::button>
                <x-filament::button color="warning" tag="a" href="{{ \App\Filament\Pages\QuizLeaderboard::getUrl(['session' => $attempt->quiz_session_id]) }}">
                    Lihat leaderboard
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Pembahasan</x-slot>

            <div class="space-y-6">
                @foreach ($attempt->answers as $i => $answer)
                    <div class="space-y-2">
                        <div class="flex items-start gap-2">
                            <x-filament::badge color="{{ $answer->is_correct ? 'success' : 'danger' }}">{{ $i + 1 }}</x-filament::badge>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $answer->question?->question }}</p>
                        </div>

                        @if ($answer->question?->imageUrl())
                            <img src="{{ $answer->question->imageUrl() }}" alt="" class="max-h-64 rounded-lg border border-gray-200 dark:border-white/10">
                        @endif

                        <ul class="space-y-1 text-sm">
                            @foreach ($answer->question?->options ?? [] as $option)
                                <li class="flex items-center gap-2 rounded-lg px-3 py-1.5
                                    {{ $option->is_correct ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : '' }}
                                    {{ (! $option->is_correct && $answer->quiz_question_option_id === $option->id) ? 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' : '' }}">
                                    <span>{{ $option->option_text }}</span>
                                    @if ($answer->quiz_question_option_id === $option->id)
                                        <span class="text-xs italic">(jawaban kamu)</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($answer->question?->explanation)
                            <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                {{ $answer->question->explanation }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

    {{-- ============================================================ --}}
    {{-- 3. Mengerjakan --}}
    {{-- ============================================================ --}}
    @else
        @php
            $answers = $attempt->answers->values();
            $current = $this->currentAnswer();
            $answeredCount = $answers->whereNotNull('quiz_question_option_id')->count();
        @endphp

        <x-filament::section>
            <x-slot name="heading">{{ $attempt->session?->title }}</x-slot>
            <x-slot name="description">
                Soal {{ $this->index + 1 }} dari {{ $answers->count() }} &middot; terjawab {{ $answeredCount }}
                &middot; ditutup {{ $attempt->session?->ends_at->translatedFormat('d M Y H:i') }}
            </x-slot>

            <x-slot name="headerEnd">
                <div x-data="{
                        elapsed: {{ (int) $attempt->started_at->diffInSeconds(now()) }},
                        get label() {
                            return String(Math.floor(this.elapsed / 60)).padStart(2, '0') + ':' + String(this.elapsed % 60).padStart(2, '0');
                        },
                    }"
                    x-init="setInterval(() => elapsed++, 1000)"
                    class="font-mono text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="label">00:00</span>
                </div>
            </x-slot>

            @if ($current)
                <div class="space-y-4">
                    @if ($current->question?->product)
                        <x-filament::badge color="gray" size="sm">{{ $current->question->product->name }}</x-filament::badge>
                    @endif

                    <p class="text-base font-medium text-gray-950 dark:text-white">{{ $current->question?->question }}</p>

                    @if ($current->question?->imageUrl())
                        <img src="{{ $current->question->imageUrl() }}" alt=""
                            class="max-h-80 rounded-lg border border-gray-200 dark:border-white/10">
                    @endif

                    <div class="space-y-2">
                        @foreach ($current->question?->options ?? [] as $option)
                            @php $selected = $current->quiz_question_option_id === $option->id; @endphp
                            <button type="button"
                                wire:click="answer('{{ $current->id }}', '{{ $option->id }}')"
                                class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm transition
                                    {{ $selected
                                        ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400'
                                        : 'border-gray-200 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5' }}">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold
                                    {{ $selected ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-300 dark:border-white/20' }}">
                                    {{ chr(65 + $loop->index) }}
                                </span>
                                <span class="text-gray-800 dark:text-gray-200">{{ $option->option_text }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
                <div class="flex gap-2">
                    <x-filament::button color="gray" size="sm" wire:click="previous" :disabled="$this->index === 0">
                        Sebelumnya
                    </x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="next" :disabled="$this->index >= $answers->count() - 1">
                        Selanjutnya
                    </x-filament::button>
                </div>

                {{ $this->finishAction }}
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                @foreach ($answers as $i => $row)
                    <button type="button" wire:click="goTo({{ $i }})"
                        class="h-8 w-8 rounded-lg border text-xs font-semibold transition
                            {{ $i === $this->index ? 'border-primary-500 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-white/10' }}
                            {{ $row->quiz_question_option_id ? 'bg-primary-500 text-white' : 'bg-white text-gray-500 dark:bg-white/5 dark:text-gray-400' }}">
                        {{ $i + 1 }}
                    </button>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

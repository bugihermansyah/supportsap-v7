@php
    use App\Enums\AnnouncementLevel;

    $announcements = $this->announcements();
    $me = auth()->id();
@endphp

<x-filament-widgets::widget>
    {{-- Bisa dilipat; posisi lipatnya diingat per browser (localStorage). --}}
    <x-filament::section
        icon="heroicon-o-megaphone"
        icon-color="primary"
        collapsible
        persist-collapsed
        collapse-id="announcement-feed">
        <x-slot name="heading">Pengumuman</x-slot>

        <x-slot name="headerEnd">
            <a href="{{ \App\Filament\Pages\Announcements::getUrl() }}"
                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Lihat semua
            </a>
        </x-slot>

        <div class="space-y-3">
            @foreach ($announcements as $announcement)
                @php
                    $level = $announcement->level instanceof AnnouncementLevel
                        ? $announcement->level
                        : AnnouncementLevel::tryFrom((string) $announcement->level);
                    $isUnread = ! $announcement->isReadBy($me);
                    $borderColor = match ($level) {
                        AnnouncementLevel::Urgent => 'border-l-danger-500',
                        AnnouncementLevel::Important => 'border-l-warning-500',
                        default => 'border-l-info-500',
                    };
                @endphp

                <div wire:key="widget-announcement-{{ $announcement->id }}"
                    class="rounded-lg border border-gray-200 border-l-4 p-3 dark:border-white/10 {{ $borderColor }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge :color="$level?->getColor() ?? 'gray'" size="sm">
                            {{ $level?->getLabel() ?? 'Info' }}
                        </x-filament::badge>

                        @if ($isUnread)
                            <span class="flex items-center gap-1 text-xs font-semibold text-danger-600 dark:text-danger-400">
                                <span class="h-2 w-2 rounded-full bg-danger-500"></span> Baru
                            </span>
                        @endif

                        <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                            {{ $announcement->created_at?->translatedFormat('d M Y') }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm font-semibold leading-snug text-gray-950 dark:text-white">
                        {{ $announcement->title }}
                    </p>

                    <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ \Illuminate\Support\Str::limit(strip_tags($announcement->body), 160) }}
                    </p>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                        @if ($announcement->quizSession)
                            <x-filament::button tag="a" size="sm" icon="heroicon-m-academic-cap"
                                href="{{ \App\Filament\Pages\Quiz::getUrl() }}"
                                class="w-full justify-center sm:w-auto">
                                Kerjakan quiz
                            </x-filament::button>
                        @endif

                        <x-filament::button tag="a" size="sm" color="gray"
                            href="{{ \App\Filament\Pages\Announcements::getUrl() }}"
                            class="w-full justify-center sm:w-auto">
                            Baca
                        </x-filament::button>

                        @if ($isUnread)
                            <x-filament::button size="sm" color="gray" icon="heroicon-m-check"
                                wire:click="markRead('{{ $announcement->id }}')"
                                class="w-full justify-center sm:ms-auto sm:w-auto">
                                Tandai dibaca
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

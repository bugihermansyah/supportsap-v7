@php
    use App\Enums\AnnouncementLevel;

    $announcements = $this->announcements();
    $unread = $this->unreadCount();
    $me = auth()->id();
@endphp

<x-filament-panels::page>
    {{-- Header ringkas: aman di layar sempit, tombol turun ke baris sendiri di HP --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if ($unread > 0)
                <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $unread }} pengumuman</span> belum dibaca
            @else
                Semua pengumuman sudah dibaca
            @endif
        </p>

        @if ($unread > 0)
            <div class="w-full sm:w-auto">
                {{ $this->markAllReadAction }}
            </div>
        @endif
    </div>

    <div class="space-y-3">
        @forelse ($announcements as $announcement)
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

            <div wire:key="announcement-{{ $announcement->id }}"
                x-data="{ open: false }"
                class="overflow-hidden rounded-xl border border-gray-200 border-l-4 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900 {{ $borderColor }}">

                <div class="space-y-3 p-4">
                    {{-- Judul + meta --}}
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="$level?->getColor() ?? 'gray'" :icon="$level?->getIcon()" size="sm">
                                {{ $level?->getLabel() ?? 'Info' }}
                            </x-filament::badge>

                            @if ($announcement->is_pinned)
                                <x-filament::badge color="gray" size="sm" icon="heroicon-m-bookmark">Disematkan</x-filament::badge>
                            @endif

                            @if ($isUnread)
                                <span class="flex items-center gap-1 text-xs font-semibold text-danger-600 dark:text-danger-400">
                                    <span class="h-2 w-2 rounded-full bg-danger-500"></span> Baru
                                </span>
                            @endif
                        </div>

                        <h2 class="text-base font-semibold leading-snug text-gray-950 dark:text-white sm:text-lg">
                            {{ $announcement->title }}
                        </h2>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $announcement->created_at?->translatedFormat('d M Y H:i') }}
                            @if ($announcement->creator?->name)
                                &middot; {{ $announcement->creator->name }}
                            @endif
                        </p>
                    </div>

                    @if ($announcement->imageUrl())
                        <img src="{{ $announcement->imageUrl() }}" alt=""
                            class="max-h-56 w-full rounded-lg object-cover sm:max-h-72">
                    @endif

                    {{-- Isi: dipotong dulu supaya daftar tetap pendek di layar HP --}}
                    <div :class="open ? '' : 'line-clamp-4'"
                        class="text-sm leading-relaxed text-gray-700 dark:text-gray-300
                            [&_a]:text-primary-600 [&_a]:underline dark:[&_a]:text-primary-400
                            [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5
                            [&_p]:mb-2 [&_strong]:font-semibold">
                        {!! $announcement->body !!}
                    </div>

                    <button type="button" @click="open = ! open"
                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        <span x-show="! open">Selengkapnya</span>
                        <span x-show="open" x-cloak>Ringkas</span>
                    </button>

                    {{-- Aksi: tumpuk penuh di HP, sejajar di layar lebar --}}
                    @if ($announcement->quizSession || $isUnread)
                        <div class="flex flex-col gap-2 border-t border-gray-100 pt-3 dark:border-white/5 sm:flex-row sm:items-center">
                            @if ($announcement->quizSession)
                                <x-filament::button tag="a" icon="heroicon-m-academic-cap"
                                    href="{{ \App\Filament\Pages\Quiz::getUrl() }}"
                                    class="w-full justify-center sm:w-auto">
                                    Kerjakan quiz
                                </x-filament::button>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $announcement->quizSession->title }}
                                    &middot; ditutup {{ $announcement->quizSession->ends_at->translatedFormat('d M Y H:i') }}
                                </span>
                            @endif

                            @if ($isUnread)
                                <x-filament::button color="gray" icon="heroicon-m-check"
                                    wire:click="markRead('{{ $announcement->id }}')"
                                    class="w-full justify-center sm:ms-auto sm:w-auto">
                                    Tandai dibaca
                                </x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-white/10">
                <x-filament::icon icon="heroicon-o-megaphone" class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada pengumuman.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>

<div class="space-y-4">
    @forelse ($mediaItems as $media)
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            @if (str_starts_with((string) $media->mime_type, 'image/'))
                <img
                    src="{{ $media->getUrl() }}"
                    alt="Form Support {{ $loop->iteration }}"
                    class="mx-auto max-h-[70vh] w-auto max-w-full object-contain"
                >
            @elseif ($media->mime_type === 'application/pdf')
                <iframe
                    src="{{ $media->getUrl() }}"
                    title="Form Support {{ $loop->iteration }}"
                    class="h-[70vh] w-full"
                ></iframe>
            @else
                <div class="flex items-center justify-between gap-4 p-4">
                    <span class="min-w-0 truncate text-sm text-gray-700 dark:text-gray-200">{{ $media->file_name }}</span>
                    <a
                        href="{{ $media->getUrl() }}"
                        download="{{ $media->file_name }}"
                        class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >
                        Unduh berkas
                    </a>
                </div>
            @endif
        </section>
    @empty
        <p class="text-sm text-gray-500">Berkas Form Support tidak ditemukan.</p>
    @endforelse
</div>

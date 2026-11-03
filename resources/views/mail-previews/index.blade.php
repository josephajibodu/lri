<x-layouts.app title="Email previews · Let’s Run Ibadan" robots="noindex, nofollow">
    <main class="flex min-h-screen flex-col bg-neutral-100 text-neutral-900 md:flex-row">
        <nav class="shrink-0 border-b border-neutral-200 bg-white p-5 md:w-80 md:border-r md:border-b-0">
            <p class="text-xs font-semibold tracking-wide text-neutral-500 uppercase">Email previews · local only</p>
            <h1 class="mt-1 text-xl font-semibold">{{ trans_choice('{1} One email|[2,*] :count emails', $previews->count()) }}</h1>
            <p class="mt-2 text-sm text-neutral-600">Sample data only. Nothing is saved or sent.</p>

            <ul class="mt-5 space-y-2">
                @foreach ($previews as $key => $preview)
                    <li>
                        <a href="{{ route('mail-previews.index', $key) }}" @class([
                            'block rounded-lg border p-3 transition',
                            'border-neutral-900 bg-neutral-900 text-white' => $key === $selected,
                            'border-neutral-200 hover:border-neutral-400' => $key !== $selected,
                        ]) @if ($key === $selected) aria-current="page" @endif>
                            <span class="block font-medium">{{ $preview['name'] }}</span>
                            <span @class(['mt-1 block text-sm', 'text-neutral-300' => $key === $selected, 'text-neutral-600' => $key !== $selected])>{{ $preview['description'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <section class="flex min-h-[80vh] flex-1 flex-col p-4 md:p-6">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm"><span class="text-neutral-500">Subject:</span> <strong>{{ $previews[$selected]['subject'] }}</strong></p>
                <a class="text-sm underline" href="{{ route('mail-previews.show', $selected) }}" target="_blank">Open on its own</a>
            </div>
            <iframe class="w-full flex-1 rounded-lg border border-neutral-200 bg-white" src="{{ route('mail-previews.show', $selected) }}" title="{{ $previews[$selected]['name'] }}"></iframe>
        </section>
    </main>
</x-layouts.app>

<div class="grid animate-pulse grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach (range(1, 4) as $i)
        <div class="rounded-xl border border-base bg-surface px-5 py-4">
            <div class="h-3 w-20 rounded bg-elevated"></div>
            <div class="mt-3 h-6 w-16 rounded bg-elevated"></div>
            <div class="mt-4 h-8 w-full rounded bg-elevated"></div>
        </div>
    @endforeach
</div>

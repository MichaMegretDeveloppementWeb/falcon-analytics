<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @foreach (range(1, 2) as $i)
        <div class="animate-pulse rounded-xl border border-base bg-surface px-5 py-4">
            <div class="mb-4 h-3.5 w-28 rounded bg-elevated"></div>
            <div class="space-y-2.5">
                @foreach (range(1, 5) as $j)
                    <div class="flex items-center gap-3">
                        <div class="h-7 w-7 shrink-0 rounded-lg bg-elevated"></div>
                        <div class="h-2 flex-1 rounded-full bg-elevated"></div>
                        <div class="h-3 w-8 rounded bg-elevated"></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

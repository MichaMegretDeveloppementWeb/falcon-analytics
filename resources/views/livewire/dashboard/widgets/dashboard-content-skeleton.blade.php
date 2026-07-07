<div class="animate-pulse space-y-8">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach (range(1, 4) as $i)
            <div class="rounded-xl border border-base bg-surface px-5 py-4">
                <div class="h-3 w-24 rounded bg-elevated"></div>
                <div class="mt-3 h-6 w-16 rounded bg-elevated"></div>
                <div class="mt-4 h-8 w-full rounded bg-elevated"></div>
            </div>
        @endforeach
    </div>
    <div class="h-64 rounded-xl border border-base bg-surface"></div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="h-56 rounded-xl border border-base bg-surface lg:col-span-7"></div>
        <div class="h-56 rounded-xl border border-base bg-surface lg:col-span-5"></div>
    </div>
</div>

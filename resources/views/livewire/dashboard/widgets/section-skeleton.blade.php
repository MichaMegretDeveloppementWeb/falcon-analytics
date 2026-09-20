<x-analytics::root area="admin" class="an:grid an:grid-cols-1 an:gap-6 an:lg:grid-cols-2">
    @foreach (range(1, 2) as $i)
        <div class="an:animate-pulse an:rounded-xl an:border an:border-default an:bg-surface an:px-5 an:py-4">
            <div class="an:mb-4 an:h-3.5 an:w-28 an:rounded an:bg-elevated"></div>
            <div class="an:space-y-2.5">
                @foreach (range(1, 5) as $j)
                    <div class="an:flex an:items-center an:gap-3">
                        <div class="an:h-7 an:w-7 an:shrink-0 an:rounded-lg an:bg-elevated"></div>
                        <div class="an:h-2 an:flex-1 an:rounded-full an:bg-elevated"></div>
                        <div class="an:h-3 an:w-8 an:rounded an:bg-elevated"></div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</x-analytics::root>

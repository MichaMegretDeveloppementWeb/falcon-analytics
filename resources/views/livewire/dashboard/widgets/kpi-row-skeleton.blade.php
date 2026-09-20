<x-analytics::root area="admin" class="an:grid an:animate-pulse an:grid-cols-2 an:gap-4 an:lg:grid-cols-4">
    @foreach (range(1, 4) as $i)
        <div class="an:rounded-xl an:border an:border-default an:bg-surface an:px-5 an:py-4">
            <div class="an:h-3 an:w-20 an:rounded an:bg-elevated"></div>
            <div class="an:mt-3 an:h-6 an:w-16 an:rounded an:bg-elevated"></div>
            <div class="an:mt-4 an:h-8 an:w-full an:rounded an:bg-elevated"></div>
        </div>
    @endforeach
</x-analytics::root>

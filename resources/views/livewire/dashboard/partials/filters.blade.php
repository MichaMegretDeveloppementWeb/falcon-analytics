<div class="an:flex an:flex-wrap an:items-center an:gap-2">
    <div class="an:w-44">
        <x-ui::select wire:model.live="period" :options="$periodOptions" />
    </div>

    @if (count($subjectOptions) > 1)
        <div class="an:w-40">
            <x-ui::select wire:model.live="subject" :options="$subjectOptions" />
        </div>
    @endif
</div>

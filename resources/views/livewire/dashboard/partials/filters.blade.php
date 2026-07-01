<div class="flex flex-wrap items-center gap-2">
    <div class="w-44">
        <x-ui.select wire:model.live="period" :options="$periodOptions" />
    </div>

    @if (count($subjectOptions) > 1)
        <div class="w-40">
            <x-ui.select wire:model.live="subject" :options="$subjectOptions" />
        </div>
    @endif
</div>

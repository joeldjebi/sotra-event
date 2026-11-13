@props(['icon' => 'sparkles', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-fill text-ink-secondary">
        <x-ui.icon :name="$icon" class="size-7" />
    </span>
    <h3 class="mt-4 text-[17px] font-semibold tracking-tight">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1.5 max-w-sm text-[15px] text-ink-secondary">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>

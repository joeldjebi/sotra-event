@props(['icon' => 'sparkles', 'title', 'description' => null])
{{-- Contenu indisponible, façon ContentUnavailableView : grand symbole gris, titre, texte secondaire. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-16 place-items-center rounded-[20px] bg-gradient-to-b from-fill-subtle to-fill text-ink-tertiary ring-1 ring-hairline">
        <x-ui.icon :name="$icon" class="size-8" />
    </span>
    <h3 class="mt-5 text-title-3">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1.5 max-w-sm text-[15px] leading-relaxed text-ink-secondary">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>

@props(['title', 'description' => null])
{{-- En-tête de groupe façon Réglages iOS : petites capitales grises au-dessus d'un groupe de cellules. --}}
<div {{ $attributes->merge(['class' => 'mb-2 px-1']) }}>
    <h3 class="text-footnote font-medium tracking-[0.02em] text-ink-secondary uppercase">{{ $title }}</h3>
    @if ($description)
        <p class="mt-0.5 text-footnote text-ink-tertiary">{{ $description }}</p>
    @endif
</div>

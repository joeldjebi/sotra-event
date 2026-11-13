@props(['label', 'name', 'options' => [], 'placeholder' => null, 'error' => null, 'hint' => null])
@php
    $message = $error ?? $errors->first($name);
    $id = $attributes->get('id', $name);
    $describedBy = $message || $hint ? $id.'-help' : null;
@endphp
{{-- Menu local façon macOS (pop-up button) : champ arrondi et double chevron. --}}
<div class="space-y-1.5">
    @if (filled($label))
        <label for="{{ $id }}" class="block px-0.5 text-footnote font-medium text-ink-secondary">{{ $label }}</label>
    @endif
    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if (! filled($label) && $placeholder) aria-label="{{ $placeholder }}" @endif
            {{ $attributes->except(['id'])->merge(['class' => 'ui-field h-11 cursor-pointer appearance-none pr-10 pl-3.5']) }}
        >
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" wire:key="{{ $name }}-opt-{{ $value }}">{{ $text }}</option>
            @endforeach
        </select>
        <x-ui.icon name="chevron-up-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-secondary" />
    </div>
    @if ($message)
        <p id="{{ $describedBy }}" class="flex items-center gap-1 px-0.5 text-footnote text-danger"><x-ui.icon name="exclamation" class="size-3.5 shrink-0" />{{ $message }}</p>
    @elseif ($hint)
        <p id="{{ $describedBy }}" class="px-0.5 text-footnote text-ink-tertiary">{{ $hint }}</p>
    @endif
</div>

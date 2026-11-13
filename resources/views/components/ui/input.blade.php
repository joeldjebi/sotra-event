@props(['label', 'name', 'type' => 'text', 'error' => null, 'hint' => null, 'floating' => false])
@php
    $message = $error ?? $errors->first($name);
    $id = $attributes->get('id', $name);
    $describedBy = $message || $hint ? $id.'-help' : null;
@endphp
@if ($floating)
    {{-- Champ à libellé flottant, façon Identifiant Apple : le libellé se loge en haut du champ à la saisie. --}}
    <div class="space-y-1.5">
        <div class="relative">
            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ $type }}"
                @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
                placeholder="{{ $attributes->get('placeholder', ' ') }}"
                @if ($message) aria-invalid="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                {{ $attributes->except(['id', 'value', 'placeholder'])->merge([
                    'class' => 'ui-field peer h-14 px-4 pt-5 pb-1.5 !text-[17px] placeholder:text-transparent focus:placeholder:text-ink-tertiary',
                ]) }}
            >
            <label for="{{ $id }}"
                   class="pointer-events-none absolute top-2 left-4 origin-left text-[12px] text-ink-secondary transition-all duration-200 ease-[var(--ease-apple)] peer-placeholder-shown:top-1/2 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:text-[17px] peer-placeholder-shown:text-ink-tertiary peer-focus:top-2 peer-focus:translate-y-0 peer-focus:text-[12px] peer-focus:text-ink-secondary">{{ $label }}</label>
        </div>
        @if ($message)
            <p id="{{ $describedBy }}" class="flex items-center gap-1 px-1 text-footnote text-danger"><x-ui.icon name="exclamation" class="size-3.5 shrink-0" />{{ $message }}</p>
        @elseif ($hint)
            <p id="{{ $describedBy }}" class="px-1 text-footnote text-ink-tertiary">{{ $hint }}</p>
        @endif
    </div>
@else
    <div class="space-y-1.5">
        @if (filled($label))
            <label for="{{ $id }}" class="block px-0.5 text-footnote font-medium text-ink-secondary">{{ $label }}</label>
        @endif
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['id', 'value'])->merge(['class' => 'ui-field h-11 px-3.5']) }}
        >
        @if ($message)
            <p id="{{ $describedBy }}" class="flex items-center gap-1 px-0.5 text-footnote text-danger"><x-ui.icon name="exclamation" class="size-3.5 shrink-0" />{{ $message }}</p>
        @elseif ($hint)
            <p id="{{ $describedBy }}" class="px-0.5 text-footnote text-ink-tertiary">{{ $hint }}</p>
        @endif
    </div>
@endif

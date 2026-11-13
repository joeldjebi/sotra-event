@props(['label', 'name', 'options' => [], 'placeholder' => null, 'error' => null, 'hint' => null])
@php
    $message = $error ?? $errors->first($name);
@endphp
<div class="space-y-1.5">
    <label for="{{ $attributes->get('id', $name) }}" class="block text-[13px] font-medium text-ink-secondary">{{ $label }}</label>
    <div class="relative">
        <select
            id="{{ $attributes->get('id', $name) }}"
            name="{{ $name }}"
            {{ $attributes->except(['id'])->merge([
                'class' => 'block w-full h-11 appearance-none rounded-xl bg-surface pl-3.5 pr-10 text-[15px] text-ink ring-1 ring-inset transition focus:outline-none focus:ring-2 '
                    .($message ? 'ring-danger/60 focus:ring-danger' : 'ring-hairline focus:ring-brand'),
            ]) }}
        >
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" wire:key="{{ $name }}-opt-{{ $value }}">{{ $text }}</option>
            @endforeach
        </select>
        <x-ui.icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-ink-tertiary" />
    </div>
    @if ($message)
        <p class="text-[13px] text-danger">{{ $message }}</p>
    @elseif ($hint)
        <p class="text-[13px] text-ink-tertiary">{{ $hint }}</p>
    @endif
</div>

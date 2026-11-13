@props(['label', 'name', 'type' => 'text', 'error' => null, 'hint' => null])
@php
    $message = $error ?? $errors->first($name);
@endphp
<div class="space-y-1.5">
    <label for="{{ $attributes->get('id', $name) }}" class="block text-[13px] font-medium text-ink-secondary">{{ $label }}</label>
    <input
        id="{{ $attributes->get('id', $name) }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
        {{ $attributes->except(['id', 'value'])->merge([
            'class' => 'block w-full h-11 rounded-xl bg-surface px-3.5 text-[15px] text-ink placeholder:text-ink-tertiary ring-1 ring-inset transition focus:outline-none focus:ring-2 '
                .($message ? 'ring-danger/60 focus:ring-danger' : 'ring-hairline focus:ring-brand'),
        ]) }}
    >
    @if ($message)
        <p class="text-[13px] text-danger">{{ $message }}</p>
    @elseif ($hint)
        <p class="text-[13px] text-ink-tertiary">{{ $hint }}</p>
    @endif
</div>

@props(['label', 'name', 'rows' => 3, 'error' => null])
@php
    $message = $error ?? $errors->first($name);
@endphp
<div class="space-y-1.5">
    <label for="{{ $attributes->get('id', $name) }}" class="block text-[13px] font-medium text-ink-secondary">{{ $label }}</label>
    <textarea id="{{ $attributes->get('id', $name) }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->except(['id'])->merge([
            'class' => 'block w-full rounded-xl bg-surface px-3.5 py-2.5 text-[15px] text-ink placeholder:text-ink-tertiary ring-1 ring-inset transition focus:outline-none focus:ring-2 '
                .($message ? 'ring-danger/60 focus:ring-danger' : 'ring-hairline focus:ring-brand'),
        ]) }}></textarea>
    @if ($message)
        <p class="text-[13px] text-danger">{{ $message }}</p>
    @endif
</div>

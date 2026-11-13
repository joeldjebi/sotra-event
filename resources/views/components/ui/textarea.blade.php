@props(['label', 'name', 'rows' => 3, 'error' => null, 'hint' => null])
@php
    $message = $error ?? $errors->first($name);
    $id = $attributes->get('id', $name);
    $describedBy = $message || $hint ? $id.'-help' : null;
@endphp
<div class="space-y-1.5">
    @if (filled($label))
        <label for="{{ $id }}" class="block px-0.5 text-footnote font-medium text-ink-secondary">{{ $label }}</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id'])->merge(['class' => 'ui-field min-h-11 resize-y px-3.5 py-2.5 leading-normal']) }}></textarea>
    @if ($message)
        <p id="{{ $describedBy }}" class="flex items-center gap-1 px-0.5 text-footnote text-danger"><x-ui.icon name="exclamation" class="size-3.5 shrink-0" />{{ $message }}</p>
    @elseif ($hint)
        <p id="{{ $describedBy }}" class="px-0.5 text-footnote text-ink-tertiary">{{ $hint }}</p>
    @endif
</div>

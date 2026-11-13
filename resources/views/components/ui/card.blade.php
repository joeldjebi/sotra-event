@props(['padding' => 'p-6'])
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline '.$padding]) }}>
    {{ $slot }}
</div>

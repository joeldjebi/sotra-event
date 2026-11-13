@props(['padding' => 'p-6'])
{{-- Surface « inset grouped » : blanche, arrondie, ombre à peine perceptible (gris anthracite en sombre). --}}
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] bg-surface shadow-[var(--shadow-card)] ring-1 ring-hairline '.$padding]) }}>
    {{ $slot }}
</div>

@props(['messages', 'viewerSide'])
{{-- Fil de négociation façon Messages : bulles du lecteur à droite (accent), interlocuteur à gauche (gris). --}}
<div class="space-y-2.5">
    @forelse ($messages as $message)
        @php($mine = $message->side === $viewerSide)
        <div wire:key="nego-{{ $message->id }}" @class(['flex flex-col', 'items-end' => $mine, 'items-start' => ! $mine])>
            <p class="mb-1 px-3 text-caption-2 font-medium text-ink-tertiary">
                {{ $message->subject->label() }} · {{ $message->user?->name ?? 'Système' }} · {{ $message->created_at->format('d/m H:i') }}
            </p>
            @if ($message->message)
                <div @class([
                    'max-w-[85%] rounded-[18px] px-3.5 py-2 text-[15px] leading-snug tracking-[-0.01em] whitespace-pre-line',
                    'rounded-br-[6px] bg-brand text-white' => $mine,
                    'rounded-bl-[6px] bg-bubble text-ink' => ! $mine,
                ])>{{ $message->message }}</div>
            @endif
        </div>
    @empty
        <p class="py-2 text-center text-footnote text-ink-tertiary">Aucun échange pour l'instant.</p>
    @endforelse
</div>

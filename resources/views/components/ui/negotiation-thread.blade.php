@props(['messages', 'viewerSide'])
{{-- Fil de négociation façon Messages : les bulles du lecteur à droite. --}}
<div class="space-y-3">
    @forelse ($messages as $message)
        @php($mine = $message->side === $viewerSide)
        <div wire:key="nego-{{ $message->id }}" @class(['flex', 'justify-end' => $mine])>
            <div @class(['max-w-[85%] rounded-2xl px-3.5 py-2.5', 'bg-brand text-white' => $mine, 'bg-fill text-ink' => ! $mine])>
                <p @class(['text-[11px] font-medium', 'text-white/75' => $mine, 'text-ink-secondary' => ! $mine])>
                    {{ $message->subject->label() }} · {{ $message->user?->name ?? 'Système' }} · {{ $message->created_at->format('d/m H:i') }}
                </p>
                @if ($message->message)
                    <p class="mt-0.5 text-[14px] whitespace-pre-line">{{ $message->message }}</p>
                @endif
            </div>
        </div>
    @empty
        <p class="text-[13px] text-ink-tertiary">Aucun échange pour l'instant.</p>
    @endforelse
</div>

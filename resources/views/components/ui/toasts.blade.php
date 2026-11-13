{{-- Notifications éphémères : $this->dispatch('notify', message: '…', tone: 'success|danger') depuis Livewire. --}}
{{-- Bannière en capsule façon Dynamic Island / HUD iOS, matériau sombre translucide. --}}
<div x-data="{ toasts: [] }"
     x-on:notify.window="const id = Date.now(); toasts.push({ id, message: $event.detail.message, tone: $event.detail.tone ?? 'success' }); setTimeout(() => toasts = toasts.filter(t => t.id !== id), 3500)"
     class="pointer-events-none fixed inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-[90] flex flex-col items-center gap-2 px-4"
     role="status" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition:enter="transition ease-[cubic-bezier(0.32,0.72,0,1)] duration-500"
             x-transition:enter-start="-translate-y-6 scale-90 opacity-0"
             x-transition:enter-end="translate-y-0 scale-100 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="pointer-events-auto flex max-w-md items-center gap-2.5 rounded-full bg-[rgb(28_28_30/0.88)] py-2.5 pr-5 pl-3 text-[14px] font-medium tracking-[-0.01em] text-white shadow-[0_8px_32px_rgb(0_0_0/0.25)] ring-1 ring-white/10 backdrop-blur-2xl backdrop-saturate-150">
            <span class="grid size-6 shrink-0 place-items-center rounded-full text-white" :class="toast.tone === 'danger' ? 'bg-[#ff453a]' : 'bg-[#30d158]'">
                <svg x-show="toast.tone !== 'danger'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>
                <svg x-show="toast.tone === 'danger'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M12 7v6m0 4h.01"/></svg>
            </span>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>

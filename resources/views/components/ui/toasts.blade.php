{{-- Notifications éphémères : $this->dispatch('notify', message: '…', tone: 'success|danger') depuis Livewire. --}}
<div x-data="{ toasts: [] }"
     x-on:notify.window="const id = Date.now(); toasts.push({ id, message: $event.detail.message, tone: $event.detail.tone ?? 'success' }); setTimeout(() => toasts = toasts.filter(t => t.id !== id), 3500)"
     class="pointer-events-none fixed inset-x-0 top-4 z-[90] flex flex-col items-center gap-2 px-4">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition class="pointer-events-auto flex items-center gap-2.5 rounded-full bg-marine-900/90 px-4 py-2.5 text-[14px] text-white shadow-[var(--shadow-float)] backdrop-blur-xl">
            <span class="grid size-5 place-items-center rounded-full" :class="toast.tone === 'danger' ? 'bg-danger' : 'bg-success'">
                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4.5 4.5L19 7"/></svg>
            </span>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>

{{-- Page générique : un composant Livewire dans la coque de l'espace. --}}
<x-layouts.app :title="$title" :subtitle="$subtitle ?? null">
    @livewire($livewireComponent)
</x-layouts.app>

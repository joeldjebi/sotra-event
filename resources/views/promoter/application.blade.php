{{-- Suivi de la demande d'un promoteur pas encore validé (ou refusé, suspendu), hors de l'espace promoteur. --}}
<x-layouts.auth title="Votre demande">
    @livewire('promoter.organisation')
    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-[14px] font-medium text-brand-text hover:underline">Se déconnecter</button>
    </form>
</x-layouts.auth>

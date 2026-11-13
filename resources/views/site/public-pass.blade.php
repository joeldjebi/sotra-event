<x-layouts.site title="Mon Pass">
    <div class="-mx-4 -my-6 bg-marine-900 px-4 py-6 sm:mx-0 sm:my-0 sm:rounded-[32px] sm:p-8">
        <h1 class="mb-5 text-[28px] font-bold tracking-tight text-white">Mon Pass</h1>
        <div class="[&_p.mt-4]:text-white/70">
            @include('site.partials.pass-card', ['pass' => $pass])
        </div>
        <p class="mt-6 text-center text-[14px] text-white/60">Connectez-vous avec le {{ $pass->holder->phone }} pour retrouver tous vos Pass.</p>
    </div>
</x-layouts.site>

@php
    // Préférence de thème : celle du compte pour les espaces professionnels, sinon celle du navigateur.
    $themePreference = auth('web')->user()?->theme ?? null;
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f5f5f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
<title>{{ $title ? $title.' · ' : '' }}ACCES TRANS</title>
{{-- Résout le thème (Système / Clair / Sombre) avant l'affichage pour éviter tout clignotement. --}}
<script>
    (() => {
        const root = document.documentElement;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const stored = (() => { try { return localStorage.getItem('eventtrans-theme'); } catch (e) { return null; } })();
        const apply = (preference) => {
            root.dataset.themePreference = preference;
            root.dataset.theme = preference === 'system' ? (media.matches ? 'dark' : 'light') : preference;
        };
        window.eventTransTheme = {
            get: () => root.dataset.themePreference,
            set: (preference) => { try { localStorage.setItem('eventtrans-theme', preference); } catch (e) {} apply(preference); },
        };
        apply(@json($themePreference) ?? stored ?? 'system');
        media.addEventListener('change', () => root.dataset.themePreference === 'system' && apply('system'));
    })();
</script>
@if (config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key')))
    {{-- Temps réel : configuration lue au chargement (et non figée à la construction de l'image). La clé est publique. --}}
    <meta name="reverb-config" content="{{ json_encode([
        'key' => config('broadcasting.connections.reverb.key'),
        'host' => config('broadcasting.connections.reverb.options.host') ?: request()->getHost(),
        'port' => (int) config('broadcasting.connections.reverb.options.port'),
        'scheme' => config('broadcasting.connections.reverb.options.scheme'),
    ]) }}">
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
@stack('head')

/*
| Temps réel (Laravel Echo + Reverb). La configuration vient de la balise <meta name="reverb-config">
| rendue par le serveur, ou à défaut des variables VITE_REVERB_* de la construction. Sans clé, le temps réel
| est simplement désactivé : les écrans se rafraîchissent alors par wire:poll. Une erreur ici ne doit jamais
| empêcher le reste de l'interface (graphiques, cartes) de fonctionner.
*/
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const readConfig = () => {
    const meta = document.querySelector('meta[name="reverb-config"]');

    if (meta) {
        try {
            return JSON.parse(meta.content);
        } catch {
            return null;
        }
    }

    return import.meta.env.VITE_REVERB_APP_KEY
        ? {
              key: import.meta.env.VITE_REVERB_APP_KEY,
              host: import.meta.env.VITE_REVERB_HOST,
              port: Number(import.meta.env.VITE_REVERB_PORT) || null,
              scheme: import.meta.env.VITE_REVERB_SCHEME,
          }
        : null;
};

const config = readConfig();

if (config?.key) {
    try {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host || window.location.hostname,
            wsPort: config.port || 80,
            wssPort: config.port || 443,
            forceTLS: (config.scheme ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.warn('Temps réel indisponible :', error);
    }
}

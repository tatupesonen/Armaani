import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// When VITE_REVERB_* env vars are set (local dev), they take precedence.
// Otherwise, fall back to the current page's location — this works in Docker
// where Nginx reverse-proxies /app and /apps to Reverb internally.
const isSecure = window.location.protocol === 'https:';
const port = window.location.port || (isSecure ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? port,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? port,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME
        ? import.meta.env.VITE_REVERB_SCHEME === 'https'
        : isSecure,
    enabledTransports: ['ws', 'wss'],
});

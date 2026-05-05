import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const configuredHost = import.meta.env.VITE_REVERB_HOST;
const configuredPort = import.meta.env.VITE_REVERB_PORT;
const configuredScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const loopbackHosts = new Set(['127.0.0.1', 'localhost', '::1']);
const shouldUseCurrentHost = window.location.protocol === 'https:' && (!configuredHost || loopbackHosts.has(configuredHost));
const wsHost = shouldUseCurrentHost ? window.location.hostname : configuredHost;
const forceTLS = window.location.protocol === 'https:' || configuredScheme === 'https';
const defaultPort = forceTLS ? 443 : 80;
const wsPort = shouldUseCurrentHost ? defaultPort : (configuredPort ?? defaultPort);

if (reverbKey && wsHost) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS,
        enabledTransports: forceTLS ? ['wss'] : ['ws', 'wss'],
    });
}

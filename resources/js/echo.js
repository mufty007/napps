/**
 * Laravel Echo over Reverb, loaded on demand (chat, live feeds) so ordinary
 * pages stay light on 3G: `const echo = await connectEcho();`
 */
let connection;

export function connectEcho() {
    connection ??= Promise.all([import('laravel-echo'), import('pusher-js')]).then(
        ([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;

            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            });

            return window.Echo;
        },
    );

    return connection;
}

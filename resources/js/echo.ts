import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import { clientConfig } from '@/lib/client-config';

(window as any).Pusher = Pusher;

const reverbConfig = clientConfig.reverb;
const scheme =
    reverbConfig?.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const port = Number(
    reverbConfig?.port ?? import.meta.env.VITE_REVERB_PORT ?? 8080,
);

const echo = new Echo({
    broadcaster: 'reverb',
    key: reverbConfig?.key ?? import.meta.env.VITE_REVERB_APP_KEY ?? '',
    wsHost: reverbConfig?.host ?? import.meta.env.VITE_REVERB_HOST ?? '',
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

export default echo;

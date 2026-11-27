import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                'resources/js/system-settings.ts',
            ],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            command: 'php artisan wayfinder:generate',
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        // 'localhost' puede resolver solo a ::1 en Windows y el navegador
        // pide 127.0.0.1; fijamos IPv4 para que @vite cargue los estilos.
        host: '127.0.0.1',
        watch: {
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/.git/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
            ],
        },
    },
});

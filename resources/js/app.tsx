import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';
import '../css/app.css';
import { initializeTheme } from './hooks/use-appearance';

createInertiaApp({
    title: (title) => {
        const dynamicAppName = (window as any).appName || import.meta.env.VITE_APP_NAME || 'Laravel';
        return title ? `${title} - ${dynamicAppName}` : dynamicAppName;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Manejo Global de Errores
router.on('invalid', (event) => {
    event.preventDefault();
    console.error('Inertia Invalid Response:', event.detail.response);
    toast.error('Error de servidor: Intente más tarde');
});

router.on('exception', (event) => {
    console.error('Inertia Exception:', event.detail.exception);
    toast.error('Error de conexión o excepcion interna: Intente más tarde');
});

// This will set light / dark mode on load...
initializeTheme();

import { useEffect, useRef } from 'react';

export function useChatViewport() {
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = containerRef.current;

        if (!container) return;

        const viewport = window.visualViewport;
        let animationFrame = 0;

        const resize = () => {
            cancelAnimationFrame(animationFrame);
            animationFrame = requestAnimationFrame(() => {
                const top = Math.max(
                    0,
                    container.getBoundingClientRect().top -
                        (viewport?.offsetTop ?? 0),
                );
                const availableHeight =
                    (viewport?.height ?? window.innerHeight) - top - 8;

                container.style.setProperty(
                    '--chat-height',
                    `${Math.max(160, availableHeight)}px`,
                );
            });
        };

        resize();
        window.addEventListener('resize', resize);
        viewport?.addEventListener('resize', resize);
        viewport?.addEventListener('scroll', resize);

        return () => {
            cancelAnimationFrame(animationFrame);
            window.removeEventListener('resize', resize);
            viewport?.removeEventListener('resize', resize);
            viewport?.removeEventListener('scroll', resize);
        };
    }, []);

    return containerRef;
}

import { useCallback, useMemo, useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(breakpoint = MOBILE_BREAKPOINT): boolean {
    const mediaQuery = useMemo(
        () =>
            typeof window === 'undefined'
                ? undefined
                : window.matchMedia(`(max-width: ${breakpoint - 1}px)`),
        [breakpoint],
    );
    const subscribe = useCallback(
        (callback: () => void) => {
            mediaQuery?.addEventListener('change', callback);

            return () => mediaQuery?.removeEventListener('change', callback);
        },
        [mediaQuery],
    );
    const getSnapshot = useCallback(
        () => mediaQuery?.matches ?? false,
        [mediaQuery],
    );

    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}

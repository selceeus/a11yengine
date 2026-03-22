import { useEffect } from 'react';

type CleanupFn = () => void;

/**
 * Subscribe to a private Echo channel and listen for events.
 * Automatically leaves the channel on unmount.
 */
export function useEchoPrivate(
    channel: string | null,
    events: Record<string, (data: unknown) => void>,
): void {
    useEffect(() => {
        if (!channel || !window.Echo) return;

        const ch = window.Echo.private(channel);
        const cleanups: CleanupFn[] = [];

        for (const [event, handler] of Object.entries(events)) {
            ch.listen(event, handler);
            cleanups.push(() => ch.stopListening(event));
        }

        return () => {
            cleanups.forEach((fn) => fn());
            window.Echo.leave(`private-${channel}`);
        };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channel]);
}

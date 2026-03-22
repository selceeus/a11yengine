import type { Auth } from '@/types/auth';

import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            flash: { status?: string | null };
            [key: string]: unknown;
        };
    }
}

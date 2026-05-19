import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';

type Props = React.ComponentProps<'main'> & {
    variant?: 'header' | 'sidebar';
};

export function AppContent({ variant = 'header', children, ...props }: Props) {
    if (variant === 'sidebar') {
        return <SidebarInset className="overflow-y-auto" {...props}>{children}</SidebarInset>;
    }

    return (
        <main
            className="flex w-full flex-1 flex-col gap-4"
            {...props}
        >
            {children}
        </main>
    );
}

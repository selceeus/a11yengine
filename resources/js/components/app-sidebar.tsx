import { Link } from '@inertiajs/react';
import { BookOpen, Building2, CircleAlert, Folder, Globe, LayoutGrid, ScanSearch } from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';
import { dashboard } from '@/routes';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Organizations',
        href: OrganizationController.index(),
        icon: Building2,
    },
    {
        title: 'Properties',
        href: PropertyController.index(),
        icon: Globe,
    },
    {
        title: 'Scans',
        href: ScanController.index(),
        icon: ScanSearch,
    },
    {
        title: 'Issues',
        href: IssueController.index(),
        icon: CircleAlert,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

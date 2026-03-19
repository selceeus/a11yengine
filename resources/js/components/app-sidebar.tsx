import { Link } from '@inertiajs/react';
import { BarChart2, BookOpen, Bot, Building2, CircleAlert, Folder, Globe, Layers, LayoutGrid, ScanSearch, ShieldAlert, Users } from 'lucide-react';
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
import TeamController from '@/actions/App/Http/Controllers/TeamController';

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
    {
        title: 'Team',
        href: TeamController.index(),
        icon: Users,
    },
    {
        title: 'AI Audits',
        href: '/audits',
        icon: Bot,
    },
    {
        title: 'Audit Dashboard',
        href: '/audits/dashboard',
        icon: BarChart2,
    },
    {
        title: 'Issue Clusters',
        href: '/issue-clusters',
        icon: Layers,
    },
    {
        title: 'Risk Advisory',
        href: '/risk-advisory',
        icon: ShieldAlert,
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

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

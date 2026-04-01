import { Link } from '@inertiajs/react';
import { BarChart2, BookOpen, CalendarClock, File, Building2, CircleAlert, FileText, Folder, Globe, Boxes, LayoutGrid, Plug, ScanSearch, ScrollText, ShieldAlert, Users } from 'lucide-react';
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
import { index as scheduledScansIndex } from '@/routes/scheduled-scans';
import { index as integrationsIndex } from '@/routes/integrations';
import IssueController from '@/actions/App/Http/Controllers/IssueController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import TeamController from '@/actions/App/Http/Controllers/TeamController';

const overviewNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const accountNavItems: NavItem[] = [
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
        title: 'Team',
        href: TeamController.index(),
        icon: Users,
    },
];

const scanningNavItems: NavItem[] = [
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
        title: 'Issue Clusters',
        href: '/issue-clusters',
        icon: Boxes,
    },
];

const auditNavItems: NavItem[] = [
    {
        title: 'Audit Dashboard',
        href: '/audits/dashboard',
        icon: BarChart2,
    },
    {
        title: 'Audit Reports',
        href: '/audits',
        icon: File,
    },
    {
        title: 'Content Audit',
        href: '/content-audit',
        icon: FileText,
    },
];

const riskNavItems: NavItem[] = [
    {
        title: 'Risk Advisory',
        href: '/risk-advisory',
        icon: ShieldAlert,
    },
    {
        title: 'Governance',
        href: '/governance',
        icon: ScrollText,
    },
];

const appSettingsNavItems: NavItem[] = [
    {
        title: 'Scheduled Scans',
        href: scheduledScansIndex(),
        icon: CalendarClock,
    },
    {
        title: 'Integrations',
        href: integrationsIndex(),
        icon: Plug,
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
                <NavMain items={overviewNavItems} label="Overview" />
                <NavMain items={accountNavItems} label="Account & Access" />
                <NavMain items={scanningNavItems} label="Scanning & Issues" />
                <NavMain items={auditNavItems} label="Auditing" />
                <NavMain items={riskNavItems} label="Risk & Compliance" />
                <NavMain items={appSettingsNavItems} label="Settings" />
            </SidebarContent>

            <SidebarFooter>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

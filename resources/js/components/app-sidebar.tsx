import { Link } from '@inertiajs/react';
import {
    FolderGit2,
    HardDrive,
    LayoutGrid,
    Map,
    Package,
    Server,
    Settings,
    Shapes,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain, type NavGroup } from '@/components/nav-main';
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
import { dashboard, steamSettings } from '@/routes';
import { index as gameInstallsIndex } from '@/routes/game-installs';
import { index as missionsIndex } from '@/routes/missions';
import { index as modsIndex } from '@/routes/mods';
import { index as presetsIndex } from '@/routes/presets';
import { index as serversIndex } from '@/routes/servers';

const navGroups: NavGroup[] = [
    {
        label: 'Management',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            {
                title: 'Game Installs',
                href: gameInstallsIndex(),
                icon: HardDrive,
            },
            { title: 'Servers', href: serversIndex(), icon: Server },
            { title: 'Workshop Mods', href: modsIndex(), icon: Package },
            { title: 'Missions', href: missionsIndex(), icon: Map },
            { title: 'Mod Presets', href: presetsIndex(), icon: Shapes },
        ],
    },
    {
        label: 'Configuration',
        items: [
            { title: 'Steam Settings', href: steamSettings(), icon: Settings },
        ],
    },
];

const footerNavItems = [
    {
        title: 'Repository',
        href: 'https://github.com/tatupesonen/armaani',
        icon: FolderGit2,
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
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

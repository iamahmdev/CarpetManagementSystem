import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BarChart3,
    Box,
    LayoutGrid,
    MapPin,
    Package,
    ShoppingCart,
    UserRound,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/users';
import type { NavGroup } from '@/components/nav-main';

type PageProps = {
    auth: {
        user: {
            role: 'owner' | 'admin' | 'sales';
        };
        permissions: string[];
    };
};

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const navGroups: NavGroup[] = [
        {
            title: 'Overview',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            title: 'Management',
            items: [
                ...(auth.permissions.includes('users.manage')
                    ? [
                          {
                              title: 'Users and Roles',
                              href: usersIndex(),
                              icon: Users,
                          },
                      ]
                    : []),
                {
                    title: 'Carpets',
                    icon: Package,
                    disabled: true,
                },
                {
                    title: 'Pieces',
                    icon: Box,
                    disabled: true,
                },
                {
                    title: 'Containers',
                    icon: Box,
                    disabled: true,
                },
                {
                    title: 'Transfers',
                    icon: ArrowLeftRight,
                    disabled: true,
                },
                {
                    title: 'Sales',
                    icon: ShoppingCart,
                    disabled: true,
                },
            ],
        },
        {
            title: 'Reports',
            items: [
                {
                    title: 'Reports',
                    icon: BarChart3,
                    disabled: true,
                },
            ],
        },
        {
            title: 'Settings',
            items: [
                {
                    title: 'Locations',
                    icon: MapPin,
                    disabled: true,
                },
                {
                    title: 'Profile',
                    href: '/profile',
                    icon: UserRound,
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="offcanvas" variant="sidebar">
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

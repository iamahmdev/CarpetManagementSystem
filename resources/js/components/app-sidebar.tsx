import { Link, usePage } from '@inertiajs/react';
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
                    icon: 'dashboard',
                },
            ],
        },
        {
            title: 'Inventory',
            items: [
                ...(auth.permissions.includes('catalog.view')
                    ? [
                          {
                              title: 'Carpets',
                              href: '/carpets',
                              icon: 'carpets' as const,
                          },
                      ]
                    : []),
                {
                    title: 'Pieces',
                    href: '/pieces',
                    icon: 'pieces',
                },
                {
                    title: 'Containers',
                    href: '/containers',
                    icon: 'containers',
                },
                {
                    title: 'Transfers',
                    href: '/transfers',
                    icon: 'transfers',
                },
            ],
        },
        {
            title: 'Sales',
            items: [
                {
                    title: 'New Sale',
                    href: '/new-sale',
                    icon: 'sale',
                },
                {
                    title: 'Sales History',
                    href: '/sales',
                    icon: 'history',
                },
                {
                    title: 'Customers',
                    href: '/customers',
                    icon: 'customers',
                },
            ],
        },
        {
            title: 'Reports',
            items: [
                {
                    title: 'Stock by Location',
                    href: '/stock-report',
                    icon: 'stock',
                },
                {
                    title: 'Container Profitability',
                    href: '/container-report',
                    icon: 'profitab',
                },
                {
                    title: 'Profit',
                    href: '/profit',
                    icon: 'profit',
                },
            ],
        },
        {
            title: 'Settings',
            items: [
                {
                    title: 'Profile',
                    href: '/profile',
                    icon: 'profile',
                },
                ...(auth.permissions.includes('locations.manage')
                    ? [
                          {
                              title: 'Locations',
                              href: '/locations',
                              icon: 'locations' as const,
                          },
                      ]
                    : []),
                ...(auth.permissions.includes('users.manage')
                    ? [
                          {
                              title: 'Users and Roles',
                              href: '/users',
                              icon: 'roles' as const,
                          },
                      ]
                    : []),
            ],
        },
    ];

    return (
        <Sidebar
            className="carpet-sidebar"
            collapsible="offcanvas"
            variant="sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="gap-2">
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

import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, UserRound, Users } from 'lucide-react';
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
            title: 'Settings',
            items: [
                {
                    title: 'Profile',
                    href: '/profile',
                    icon: UserRound,
                },
                ...(auth.permissions.includes('users.manage')
                    ? [
                          {
                              title: 'Users and Roles',
                              href: usersIndex(),
                              icon: Users,
                          },
                      ]
                    : []),
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

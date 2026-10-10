import { dashboard } from '@/routes';
import type { DemoIconName } from '@/components/demo-icon';
import type { NavItem } from '@/types';

export type SidebarNavItem = Omit<NavItem, 'icon'> & {
    icon: DemoIconName;
};

export type NavGroup = {
    title: string;
    items: SidebarNavItem[];
};

export function getAppNavGroups(permissions: string[]): NavGroup[] {
    return [
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
                ...(permissions.includes('catalog.view')
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
                ...(permissions.includes('sales.view')
                    ? [
                          {
                              title: 'Sales History',
                              href: '/sales',
                              icon: 'history' as const,
                          },
                          {
                              title: 'Customers',
                              href: '/customers',
                              icon: 'customers' as const,
                          },
                      ]
                    : []),
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
                ...(permissions.includes('reports.receivables')
                    ? [
                          {
                              title: 'Receivables',
                              href: '/receivables',
                              icon: 'receivables' as const,
                          },
                      ]
                    : []),
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
                ...(permissions.includes('locations.manage')
                    ? [
                          {
                              title: 'Locations',
                              href: '/locations',
                              icon: 'locations' as const,
                          },
                      ]
                    : []),
                ...(permissions.includes('users.manage')
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
}

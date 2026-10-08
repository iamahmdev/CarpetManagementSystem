import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { DemoIcon } from '@/components/demo-icon';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

const pageTitles: Record<string, string> = {
    '/dashboard': 'Dashboard',
    '/carpets': 'Carpets',
    '/pieces': 'Pieces',
    '/containers': 'Containers',
    '/transfers': 'Transfers',
    '/new-sale': 'New Sale',
    '/sales': 'Sales History',
    '/customers': 'Customers',
    '/stock-report': 'Stock by Location',
    '/container-report': 'Container Profitability',
    '/profit': 'Profit',
    '/profile': 'Profile',
    '/settings/profile': 'Profile',
    '/settings/security': 'Security',
    '/settings/appearance': 'Appearance',
    '/locations': 'Locations',
    '/users': 'Users and Roles',
};

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<{
        auth: {
            user: {
                role: 'owner' | 'admin' | 'sales';
            };
        };
    }>();
    const { auth } = page.props;
    const { toggleSidebar } = useSidebar();
    const [viewingAs, setViewingAs] = useState(auth.user.role);
    const path = new URL(
        page.url,
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
    ).pathname;
    const pageTitle =
        pageTitles[path] ?? breadcrumbs[breadcrumbs.length - 1]?.title;
    const routeBreadcrumbs: BreadcrumbItemType[] = pageTitle
        ? [{ title: pageTitle, href: path }]
        : [];

    return (
        <header className="carpet-topbar flex shrink-0 items-center transition-[width,height] ease-linear">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="-ml-1 min-[901px]:hidden"
                    aria-label="Open navigation menu"
                    onClick={toggleSidebar}
                >
                    <DemoIcon name="menu" />
                    <span className="sr-only">Open navigation menu</span>
                </Button>
                <Breadcrumbs breadcrumbs={routeBreadcrumbs} />
            </div>
            <label className="carpet-search">
                <DemoIcon name="search" />
                <input
                    type="search"
                    placeholder="Search piece ID or barcode"
                    aria-label="Search piece ID or barcode"
                />
            </label>
            <label className="carpet-role-view">
                <span>Viewing as</span>
                <select
                    value={viewingAs}
                    onChange={(event) => {
                        const selectedRole = event.target.value;

                        if (
                            selectedRole === 'owner' ||
                            selectedRole === 'admin' ||
                            selectedRole === 'sales'
                        ) {
                            setViewingAs(selectedRole);
                        }
                    }}
                    aria-label="Viewing as"
                >
                    <option value="owner">Owner</option>
                    <option value="admin">Admin</option>
                    <option value="sales">Sales</option>
                </select>
            </label>
        </header>
    );
}

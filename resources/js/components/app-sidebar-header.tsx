import { usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { dashboard } from '@/routes';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { url } = usePage();
    const path = new URL(
        url,
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
    ).pathname;
    const routeBreadcrumbs: BreadcrumbItemType[] =
        path === '/dashboard'
            ? [
                  { title: 'Overview', href: dashboard() },
                  { title: 'Dashboard', href: dashboard() },
              ]
            : path === '/profile' ||
                path.startsWith('/settings/profile') ||
                path.startsWith('/settings/security') ||
                path.startsWith('/settings/appearance')
              ? [
                    { title: 'Settings', href: '/profile' },
                    { title: 'Profile', href: '/profile' },
                ]
              : path.startsWith('/users')
                ? [
                      { title: 'Settings', href: '/profile' },
                      { title: 'Users and Roles', href: '/users' },
                  ]
                : breadcrumbs;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger
                    className="-ml-1 min-[900px]:hidden"
                    aria-label="Open navigation menu"
                />
                <Breadcrumbs breadcrumbs={routeBreadcrumbs} />
            </div>
        </header>
    );
}

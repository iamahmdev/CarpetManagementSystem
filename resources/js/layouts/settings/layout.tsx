import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="settings-layout">
            <div className="settings-layout-inner">
                <aside className="settings-layout-nav">
                    <nav className="demo-settings-nav" aria-label="Settings">
                        {sidebarNavItems.map((item, index) => {
                            const isActive =
                                (item.title === 'Profile' &&
                                    currentUrl === '/profile') ||
                                isCurrentOrParentUrl(item.href);

                            return (
                                <Link
                                    key={`${toUrl(item.href)}-${index}`}
                                    href={item.href}
                                    aria-current={isActive ? 'page' : undefined}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>
                <section className="settings-layout-content">
                    {children}
                </section>
            </div>
        </div>
    );
}

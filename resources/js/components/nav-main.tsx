import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

type DisabledNavItem = Pick<NavItem, 'title' | 'icon'> & {
    disabled: true;
};

export type NavGroup = {
    title: string;
    items: (NavItem | DisabledNavItem)[];
};

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    return (
        <>
            {groups.map(
                (group) =>
                    group.items.length > 0 && (
                        <SidebarGroup key={group.title} className="px-2 py-0">
                            <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild={!('disabled' in item)}
                                            disabled={'disabled' in item}
                                            isActive={
                                                !('disabled' in item) &&
                                                (isCurrentUrl(
                                                    item.href,
                                                    undefined,
                                                    true,
                                                ) ||
                                                    (item.title === 'Profile' &&
                                                        currentUrl.startsWith(
                                                            '/settings/',
                                                        )))
                                            }
                                            tooltip={{
                                                children: item.title,
                                            }}
                                        >
                                            {'disabled' in item ? (
                                                <>
                                                    {item.icon && <item.icon />}
                                                    <span>{item.title}</span>
                                                </>
                                            ) : (
                                                <Link
                                                    href={item.href}
                                                    prefetch
                                                    onClick={() => {
                                                        if (isMobile) {
                                                            setOpenMobile(
                                                                false,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    {item.icon && <item.icon />}
                                                    <span>{item.title}</span>
                                                </Link>
                                            )}
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    ),
            )}
        </>
    );
}

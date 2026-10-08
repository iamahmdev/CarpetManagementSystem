import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { DemoIcon } from '@/components/demo-icon';
import type { NavGroup } from '@/components/app-nav-groups';
import { useCurrentUrl } from '@/hooks/use-current-url';

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
                                            asChild
                                            isActive={
                                                isCurrentUrl(
                                                    item.href,
                                                    undefined,
                                                    true,
                                                ) ||
                                                (item.title === 'Profile' &&
                                                    currentUrl.startsWith(
                                                        '/settings/',
                                                    ))
                                            }
                                            tooltip={{
                                                children: item.title,
                                            }}
                                        >
                                            <Link
                                                href={item.href}
                                                prefetch
                                                onClick={() => {
                                                    if (isMobile) {
                                                        setOpenMobile(false);
                                                    }
                                                }}
                                            >
                                                <DemoIcon name={item.icon} />
                                                <span>{item.title}</span>
                                            </Link>
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

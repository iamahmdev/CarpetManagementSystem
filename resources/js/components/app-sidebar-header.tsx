import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { getAppNavGroups } from '@/components/app-nav-groups';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { DemoIcon } from '@/components/demo-icon';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<{
        auth: {
            permissions: string[];
        };
    }>();
    const { auth } = page.props;
    const [pieceCode, setPieceCode] = useState('');
    const [pieceSearchError, setPieceSearchError] = useState('');
    const { toggleSidebar } = useSidebar();
    const path = new URL(
        page.url,
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
    ).pathname;
    const route = getAppNavGroups(auth.permissions)
        .flatMap((group) => group.items.map((item) => ({ group, item })))
        .find(
            ({ item }) =>
                new URL(
                    toUrl(item.href),
                    typeof window === 'undefined'
                        ? 'http://localhost'
                        : window.location.origin,
                ).pathname === path,
        );
    const routeBreadcrumbs: BreadcrumbItemType[] = route
        ? [
              { title: route.group.title, href: route.item.href },
              { title: route.item.title, href: route.item.href },
          ]
        : breadcrumbs.length > 0
          ? breadcrumbs
          : [];

    async function searchPiece(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const input = pieceCode.trim().toUpperCase();
        if (!input) {
            return;
        }
        setPieceSearchError('');
        const numericCode = input.match(/^(?:CP-)?(\d{1,6})$/);
        const code = numericCode
            ? `CP-${numericCode[1].padStart(6, '0')}`
            : input;
        try {
            const params = new URLSearchParams({ code });
            const response = await fetch(`/pieces/search?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const result = (await response.json()) as {
                message?: string;
                url?: string;
            };
            if (!response.ok || !result.url) {
                setPieceSearchError(result.message ?? 'Unable to search for piece.');
                return;
            }
            router.get(result.url);
        } catch {
            setPieceSearchError('Unable to search for piece. Please try again.');
        }
    }

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
            <div>
                <form className="carpet-search" onSubmit={searchPiece}>
                    <DemoIcon name="search" />
                    <input
                        type="search"
                        placeholder="Search piece ID or barcode"
                        aria-label="Search piece ID or barcode"
                        value={pieceCode}
                        onChange={(event) => setPieceCode(event.target.value)}
                    />
                </form>
                {pieceSearchError && (
                    <p className="ledger-validation-error" role="status">
                        {pieceSearchError}
                    </p>
                )}
            </div>
        </header>
    );
}

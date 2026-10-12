import { Form, Head, Link, usePage } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index as usersIndex, create, edit, show } from '@/routes/users';
import type { Auth, User } from '@/types';

type UserListItem = Pick<User, 'id' | 'name' | 'email' | 'role'> & {
    created_at: string;
};

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    auth: Auth;
    permissions: string[];
    rolePermissions: Record<string, string[]>;
    users: {
        data: UserListItem[];
        links: PaginatorLink[];
    };
};

export default function UsersIndex() {
    const { auth, users, permissions, rolePermissions } =
        usePage<Props>().props;
    const canManageUsers = auth.permissions.includes('users.manage');
    const canDeleteUsers = canManageUsers && auth.user.role === 'owner';

    return (
        <>
            <Head title="Users" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Users"
                        description="View and manage system users."
                    />
                    {canManageUsers && (
                        <Button asChild>
                            <Link href={create()}>Create user</Link>
                        </Button>
                    )}
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-3 text-base font-semibold">
                        Role permissions
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2">Permission</th>
                                    {Object.keys(rolePermissions).map(
                                        (role) => (
                                            <th
                                                className="px-3 py-2 capitalize"
                                                key={role}
                                            >
                                                {role}
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {permissions.map((permission) => (
                                    <tr className="border-t" key={permission}>
                                        <td className="px-3 py-2">
                                            {{
                                                'stock.view':
                                                    'View pieces and stock',
                                                'containers.manage':
                                                    'Receive containers',
                                                'transfers.manage':
                                                    'Transfer stock',
                                                'costs.view':
                                                    'See cost prices',
                                                'reports.profit':
                                                    'Profit reports',
                                                'reports.receivables':
                                                    'Receivables report',
                                            }[permission] ?? permission}
                                        </td>
                                        {Object.entries(rolePermissions).map(
                                            ([role, grants]) => (
                                                <td
                                                    className="px-3 py-2"
                                                    key={`${permission}-${role}`}
                                                >
                                                    {grants.includes(permission)
                                                        ? 'Yes'
                                                        : '—'}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-4 py-3">{user.name}</td>
                                    <td className="px-4 py-3">{user.email}</td>
                                    <td className="px-4 py-3 capitalize">
                                        {user.role}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <Link
                                                href={show(user.id)}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                View
                                            </Link>
                                            {canManageUsers && (
                                                <Link
                                                    href={edit(user.id)}
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                            {canDeleteUsers &&
                                                user.id !== auth.user.id && (
                                                    <Form
                                                        {...UserController.destroy.form(
                                                            user.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        onBefore={() =>
                                                            window.confirm(
                                                                `Delete ${user.name}?`,
                                                            )
                                                        }
                                                    >
                                                        <button
                                                            type="submit"
                                                            className="text-destructive underline-offset-4 hover:underline"
                                                        >
                                                            Delete
                                                        </button>
                                                    </Form>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav className="flex flex-wrap gap-2" aria-label="Pagination">
                    {users.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url}
                                className={`rounded border px-3 py-1 text-sm ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-muted'
                                }`}
                            >
                                {link.label
                                    .replaceAll('&laquo;', '«')
                                    .replaceAll('&raquo;', '»')}
                            </Link>
                        ) : (
                            <span
                                key={`${link.label}-${index}`}
                                className="rounded border px-3 py-1 text-sm text-muted-foreground"
                            >
                                {link.label
                                    .replaceAll('&laquo;', '«')
                                    .replaceAll('&raquo;', '»')}
                            </span>
                        ),
                    )}
                </nav>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: usersIndex(),
        },
    ],
};

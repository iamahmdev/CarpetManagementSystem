import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index, edit } from '@/routes/users';
import type { Auth, User } from '@/types';

type Props = {
    auth: Auth;
    user: Pick<User, 'id' | 'name' | 'email' | 'role'> & {
        created_at: string;
    };
};

export default function ShowUser() {
    const { auth, user } = usePage<Props>().props;

    return (
        <>
            <Head title={user.name} />
            <div className="max-w-2xl space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading title={user.name} description="User details" />
                    {auth.permissions.includes('users.update') && (
                        <Button asChild>
                            <Link href={edit(user.id)}>Edit user</Link>
                        </Button>
                    )}
                </div>

                <dl className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted-foreground">Name</dt>
                        <dd>{user.name}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">Email</dt>
                        <dd>{user.email}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">Role</dt>
                        <dd className="capitalize">{user.role}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Created
                        </dt>
                        <dd>{user.created_at}</dd>
                    </div>
                </dl>

                <Link
                    href={index()}
                    className="text-primary underline-offset-4 hover:underline"
                >
                    Back to users
                </Link>
            </div>
        </>
    );
}

ShowUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: 'User details', href: index() },
    ],
};

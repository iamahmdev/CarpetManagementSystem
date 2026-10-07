import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import UserForm from '@/components/user-form';
import { index } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    user: Pick<User, 'id' | 'name' | 'email' | 'role'>;
    roles: string[];
};

export default function EditUser({ user, roles }: Props) {
    return (
        <>
            <Head title={`Edit ${user.name}`} />
            <div className="p-4">
                <UserForm
                    action={UserController.update.url(user.id)}
                    method="put"
                    roles={roles}
                    user={user}
                />
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: 'Edit user', href: index() },
    ],
};

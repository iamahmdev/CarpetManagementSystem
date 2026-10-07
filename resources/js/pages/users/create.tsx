import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import UserForm from '@/components/user-form';
import { index } from '@/routes/users';

type Props = {
    roles: string[];
};

export default function CreateUser({ roles }: Props) {
    return (
        <>
            <Head title="Create user" />
            <div className="p-4">
                <UserForm
                    action={UserController.store.url()}
                    method="post"
                    roles={roles}
                />
            </div>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: 'Create user', href: index() },
    ],
};

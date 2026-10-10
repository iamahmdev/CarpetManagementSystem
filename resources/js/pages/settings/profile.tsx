import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

const permissionLabels: Record<string, string> = {
    'users.manage': 'Manage users and roles',
    'stock.view': 'View pieces and stock',
    'costs.view': 'View costs',
    'reports.profit': 'Profit report',
    'reports.receivables': 'Receivables report',
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile" />

            <main className="profile-view">
                <header className="page-head">
                    <div>
                        <h1>Profile</h1>
                        <p className="sub">Your account details.</p>
                    </div>
                </header>

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="demo-card profile-card"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="demo-profile-row">
                                <div className="demo-field">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="demo-field">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.email}
                                    />
                                </div>
                            </div>

                            <div className="profile-role">
                                <div className="small-muted">Role</div>
                                <span className="demo-role-badge">
                                    {auth.user.role}
                                </span>
                            </div>

                            <div className="profile-permissions">
                                <div className="small-muted">
                                    What this role can do
                                </div>
                                <div>
                                    {auth.permissions.map((permission) => (
                                        <span
                                            className="demo-chip"
                                            key={permission}
                                        >
                                            {permissionLabels[permission] ??
                                                permission}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="profile-actions">
                                <Button
                                    disabled={processing}
                                    className="demo-primary-button"
                                    data-test="update-profile-button"
                                >
                                    Save changes
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};

import { Form } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { User } from '@/types';

type EditableUser = Pick<User, 'id' | 'name' | 'email' | 'role'>;

type Props = {
    action: string;
    method: 'post' | 'put';
    roles: string[];
    user?: EditableUser;
};

export default function UserForm({ action, method, roles, user }: Props) {
    return (
        <div className="space-y-6">
            <Heading
                title={user ? 'Edit user' : 'Create user'}
                description={
                    user
                        ? 'Update this user’s account details and role.'
                        : 'Add a user and assign their role.'
                }
            />

            <Form
                action={action}
                method={method}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="max-w-xl space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                maxLength={255}
                                autoComplete="name"
                                defaultValue={user?.name}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                maxLength={255}
                                autoComplete="email"
                                defaultValue={user?.email}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>
                            <select
                                id="role"
                                name="role"
                                required
                                defaultValue={user?.role ?? 'sales'}
                                className="h-9 w-full rounded-md border border-input bg-demo-card px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role.charAt(0).toUpperCase() +
                                            role.slice(1)}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.role} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {user ? 'New password (optional)' : 'Password'}
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                required={!user}
                                autoComplete="new-password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required={!user}
                                autoComplete="new-password"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button disabled={processing}>
                            {user ? 'Save changes' : 'Create user'}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}

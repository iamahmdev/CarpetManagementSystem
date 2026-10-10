<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'created_at'])
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'permissions' => config('permissions'),
            'rolePermissions' => config('roles'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'roles' => $this->roles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    public function show(User $user): Response
    {
        return Inertia::render('users/show', [
            'user' => [
                ...$this->userDetails($user),
                'created_at' => $user->created_at?->toDateTimeString(),
            ],
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'user' => $this->userDetails($user),
            'roles' => $this->roles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if ($user->email !== $validated['email']) {
            $user->email_verified_at = null;
        }

        $user->fill($validated)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return array_map(
            fn (UserRole $role): string => $role->value,
            UserRole::cases(),
        );
    }

    /**
     * @return array{id: int, name: string, email: string, role: string}
     */
    private function userDetails(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}

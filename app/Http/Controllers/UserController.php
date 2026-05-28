<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $manageableRoles = $actor->manageableRoles();

        $users = User::query()
            ->whereIn('role', $manageableRoles)
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->userPayload($user, $actor))
            ->values();

        return response()->json([
            'users' => $users,
            'allowed_roles' => $this->roleOptions($manageableRoles),
            'can_create_superadmin' => $actor->isSuperAdmin() && (bool) config('auth_roles.allow_creating_superadmins'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $manageableRoles = $actor->manageableRoles();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in($manageableRoles)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($actor->canManageRole($validated['role']), 403);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($this->userPayload($user, $actor), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);

        $manageableRoles = $actor->manageableRoles();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in($manageableRoles)],
            'is_active' => ['required', 'boolean'],
        ]);

        abort_unless($actor->canManageRole($validated['role']), 403);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json($this->userPayload($user, $actor));
    }

    public function enable(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);

        $user->forceFill(['is_active' => true])->save();

        return response()->json($this->userPayload($user, $actor));
    }

    public function disable(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);

        $user->forceFill(['is_active' => false])->save();

        return response()->json($this->userPayload($user, $actor));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->canManageUser($user), 403);

        $user->delete();

        return response()->json(null, 204);
    }

    private function userPayload(User $user, User $actor): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'is_active' => (bool) $user->is_active,
            'can_edit' => $actor->canManageUser($user),
            'can_disable' => $actor->canManageUser($user),
            'can_delete' => $actor->canManageUser($user),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    private function roleOptions(array $roles): array
    {
        return collect($roles)
            ->map(fn (string $role) => [
                'value' => $role,
                'label' => config('auth_roles.roles.' . $role, ucfirst($role)),
            ])
            ->values()
            ->all();
    }
}

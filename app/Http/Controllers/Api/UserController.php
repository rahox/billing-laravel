<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::query()->with('roles:id,name');

        if ($role = $request->query('role')) {
            $query->role($role);
        }

        $resellerId = $request->query('reseller_id');
        if ($user->hasRole('reseller')) {
            // Reseller hanya boleh melihat tim sales miliknya sendiri, atau daftar collector (global).
            if ($role === 'sales') {
                $resellerId = $user->id;
            } elseif (! in_array($role, ['collector', null], true)) {
                abort(403);
            }
        }
        if ($resellerId) {
            $query->where('parent_reseller_id', $resellerId);
        }

        return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['reseller', 'sales', 'collector'])],
            'parent_reseller_id' => ['nullable', 'exists:users,id'],
            'commission_type' => ['nullable', 'in:flat,percentage'],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'parent_reseller_id' => $data['parent_reseller_id'] ?? null,
            'commission_type' => $data['commission_type'] ?? null,
            'commission_value' => $data['commission_value'] ?? null,
        ]);
        $user->assignRole($data['role']);

        return response()->json($user->load('roles'), 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load('roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'commission_type' => ['nullable', 'in:flat,percentage'],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user->update($data);

        return response()->json($user->load('roles'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Role;
use App\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles')->orderBy('name');
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }
        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('users.index', [
            'users' => $query->paginate(20)->appends($request->query()),
        ]);
    }

    public function create()
    {
        $roles = Role::orderBy('label')->get();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($data['role_id']);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role->name,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        ActivityLogger::log('Created user', 'Users', 'Created ' . $user->email . ' with role ' . $role->label . '.', $user);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created and assigned to ' . $role->label . '.');
    }

    public function show($id)
    {
        $user = User::with(['roles.permissions'])->findOrFail($id);

        return view('users.show', compact('user'));
    }

    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = Role::orderBy('label')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:30',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'required|boolean',
        ]);
        if ($request->filled('password')) {
            $data['password'] = $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ])['password'];
        }
        $role = Role::findOrFail($data['role_id']);
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->is_active = (bool) $data['is_active'];
        $user->role = $role->name;
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->roles()->sync([$role->id]);

        return redirect()->route('users.show', $user->id)
            ->with('success', 'User details updated.');
    }

    public function status(Request $request, $id)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $user = User::findOrFail($id);
        $user->is_active = (bool) $data['is_active'];
        $user->save();

        return redirect()->route('users.index')
            ->with('success', $user->is_active ? 'User activated.' : 'User deactivated.');
    }
}

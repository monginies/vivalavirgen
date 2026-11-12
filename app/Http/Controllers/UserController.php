<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['active' => true])]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is(auth()->user())) {
            // Evita que el superusuario se quite permisos o se desactive a sí mismo.
            $data['is_superadmin'] = true;
            $data['active'] = true;
        }
        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'No puedes borrarte a ti mismo.');
        $user->delete();

        return redirect()->route('users.index')->with('status', 'Usuario eliminado.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
        $data['is_superadmin'] = $request->boolean('is_superadmin');
        $data['active'] = $request->boolean('active');

        return $data;
    }
}

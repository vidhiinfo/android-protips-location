<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('module:users,manage')];
    }

    public function index(Request $request)
    {
        $users = User::with(['state', 'district'])
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')
                ->orWhere('username', 'like', '%'.$request->query('q').'%')))
            ->orderBy('role')->orderBy('name')->paginate(30)->withQueryString();

        return view('users.index', ['users' => $users]);
    }

    public function create()
    {
        return view('users.form', $this->formData() + ['user' => new User(['role' => 'state_admin', 'active' => true])]);
    }

    public function store(Request $request)
    {
        $user = User::create($this->validated($request));
        AuditLog::record('create', $user, $user->username);

        return redirect()->route('users.index')->with('ok', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', $this->formData() + ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id && (($data['role'] ?? '') !== 'super_admin' || empty($data['active']))) {
            return back()->withErrors(['role' => 'You cannot remove your own super admin access.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $user->tokens()->delete(); // sign out mobile apps after a password reset
        }
        $user->update($data);
        AuditLog::record('update', $user, $user->username);

        return redirect()->route('users.index')->with('ok', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete yourself.');
        $user->update(['active' => false]);
        $user->tokens()->delete();
        AuditLog::record('disable', $user, $user->username);

        return back()->with('ok', 'User disabled.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'username' => ['required', 'alpha_dash', 'max:50', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'mobile' => 'nullable|string|max:15',
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'state_id' => 'nullable|required_if:role,state_admin,district_admin|exists:states,id',
            'district_id' => 'nullable|required_if:role,district_admin|exists:districts,id',
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'active' => 'nullable|boolean',
        ]);
        $data['active'] = $request->boolean('active');
        if (in_array($data['role'], ['super_admin', 'national_staff'], true)) {
            $data['state_id'] = $data['district_id'] = null;
        } elseif ($data['role'] === 'state_admin') {
            $data['district_id'] = null;
        }
        if (! empty($data['district_id']) && District::find($data['district_id'])?->state_id != $data['state_id']) {
            abort(422, 'District does not belong to the selected state.');
        }
        // An email column is NOT NULL in the default users table; fall back to a placeholder.
        $data['email'] ??= $data['username'].'@users.local';

        return $data;
    }

    private function formData(): array
    {
        return [
            'states' => State::orderBy('name')->get(),
            'districtsByState' => District::orderBy('name')->get(['id', 'state_id', 'name'])->groupBy('state_id'),
        ];
    }
}

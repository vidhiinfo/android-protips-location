<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);
        $user = User::where('username', $data['username'])->orWhere('email', $data['username'])->first();

        try {
            $valid = $user && $user->active && Hash::check($data['password'], $user->password);
        } catch (\RuntimeException $e) {
            report($e);
            throw ValidationException::withMessages(['username' => 'This account\'s password must be reset by the administrator.']);
        }
        if (! $valid) {
            throw ValidationException::withMessages(['username' => 'Invalid username or password.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        auth()->setUser($user);
        AuditLog::record('api_login', null, $data['device_name']);

        return ['token' => $user->createToken($data['device_name'])->plainTextToken, 'user' => $this->profile($user)];
    }

    /** Public branding for the app's login screen: organisation, theme, leader profile, latest app version. */
    public function branding()
    {
        return [
            'org_name' => setting('org_name'),
            'org_short_name' => setting('org_short_name'),
            'theme_color' => setting('theme_color'),
            'logo_url' => branding_image_url('logo'),
            'leader_on_login' => (bool) setting('leader_on_login'),
            'leader_name' => setting('leader_name'),
            'leader_designations' => leader_designations(),
            'leader_photo_url' => branding_image_url('leader'),
            'app_version' => setting('app_version'),
            'app_download_url' => setting('app_apk_path') ? route('app.download') : null,
            'app_page_url' => setting('app_public') ? route('app.public') : null,
        ];
    }

    public function me(Request $request)
    {
        return $this->profile($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /** Profile + effective permissions + branding, so the app can build its menu dynamically. */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'jurisdiction' => $user->jurisdictionLabel(),
            'state_id' => $user->state_id,
            'district_id' => $user->district_id,
            'permissions' => collect(config('office.modules'))->keys()->mapWithKeys(fn ($m) => [$m => $user->access($m)]),
            'branding' => [
                'org_name' => setting('org_name'),
                'org_short_name' => setting('org_short_name'),
                'president_name' => setting('president_name'),
                'theme_color' => setting('theme_color'),
                'logo_url' => branding_image_url('logo'),
                'leader_name' => setting('leader_name'),
                'leader_designations' => leader_designations(),
                'leader_photo_url' => branding_image_url('leader'),
            ],
        ];
    }
}

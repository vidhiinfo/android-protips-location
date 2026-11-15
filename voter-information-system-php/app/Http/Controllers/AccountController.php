<?php

namespace App\Http\Controllers;

use App\Models\AppUser;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function form() { return view('account.password'); }

    public function change(Request $r)
    {
        $u = AppUser::whereRaw('lower(username) = ?', [mb_strtolower((string) $this->user()->username())])->first();
        $new = (string) $r->input('newPassword');
        $error = match (true) {
            !$u || !AppUser::checkPassword((string) $r->input('current'), $u->password_hash) => 'account.error.current',
            strlen($new) < 8 => 'validation.password',
            $new !== (string) $r->input('confirm') => 'account.error.mismatch',
            default => null,
        };
        if ($error) return view('account.password', ['error' => $error]);
        $u->update(['password_hash' => AppUser::hashPassword($new)]);
        $this->audit()->log('PASSWORD_CHANGE', 'USER', $u->id);
        return redirect($this->user()->landingPage())->with('success', 'flash.passwordChanged');
    }
}

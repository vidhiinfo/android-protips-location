<?php

namespace App\Http\Controllers;

use App\Services\Auth\Defaults;
use App\Services\Auth\LoginService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function show(VoterService $voters, Defaults $defaults)
    {
        $defaults->ensure();
        return view('login', ['loginAssemblies' => $voters->assemblies()]);
    }

    public function login(Request $r, LoginService $login)
    {
        $error = $login->attempt((string) $r->input('username'), (string) $r->input('password'), $r->input('assemblyNo'));
        if ($error === 'otp') return redirect('/login/otp');
        if ($error === 'otp-setup') return redirect('/login/otp/setup');
        if ($error) return redirect('/login?error=' . $error);
        return redirect($this->user()->landingPage());
    }

    public function logout()
    {
        $this->user()->logout();
        return redirect('/login?logout=1');
    }

    public function home() { return redirect($this->user()->landingPage()); }
}

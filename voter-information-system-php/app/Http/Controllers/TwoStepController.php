<?php

namespace App\Http\Controllers;

use App\Models\AppUser;
use App\Services\Auth\LoginService;
use App\Services\Auth\TwoStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/** Second login step, its set-up (at login or from Account > Two-step login), recovery codes and the admin reset. */
class TwoStepController extends Controller
{
    private const SETUP = 'vis.otp.setup';
    private const CODES = 'vis.otp.codes';

    public function __construct(private TwoStep $two, private LoginService $login) {}

    /** User waiting for the second step (password already right); expires after 10 minutes. */
    private function pending(): ?array
    {
        $p = session(TwoStep::PENDING);
        if (!$p || $p['at'] < time() - 600) { session()->forget(TwoStep::PENDING); return null; }
        return $p;
    }

    private function pendingUser(): ?AppUser { return ($p = $this->pending()) ? AppUser::find($p['user_id']) : null; }

    private function me(): ?AppUser
    {
        return $this->user()->check() ? AppUser::whereRaw('lower(username) = ?', [mb_strtolower((string) $this->user()->username())])->first() : $this->pendingUser();
    }

    private function mask(?string $m): string { return $m ? str_repeat('•', max(0, strlen($m) - 4)) . substr($m, -4) : ''; }

    // ------------------------------------------------------------------ login: enter the code

    public function challenge()
    {
        $u = $this->pendingUser();
        if (!$u) return redirect('/login?error=expired');
        if (!$u->otp_method) return redirect('/login/otp/setup');
        $sent = null;
        if ($u->otp_method !== 'app' && !session()->has('vis.otp.sent')) {
            $err = $this->two->sendCode($u, $u->otp_method);
            session(['vis.otp.sent' => true]);
            if ($err) $sent = t($err); 
        }
        return view('auth.otp', ['u' => $u, 'error' => $sent, 'masked' => $this->mask($u->mobile)]);
    }

    public function verify(Request $r)
    {
        $p = $this->pending();
        $u = $p ? AppUser::find($p['user_id']) : null;
        if (!$u) return redirect('/login?error=expired');
        if ($this->two->verify($u, (string) $r->input('code'))) {
            session()->forget('vis.otp.sent');
            $this->login->finish($p['payload'], $u->id, 'two-step ' . $u->otp_method);
            $res = redirect($this->user()->landingPage());
            return $r->boolean('trust') ? $res->withCookie(TwoStep::trustCookie($u)) : $res;
        }
        $p['tries']++;
        $this->audit()->log('LOGIN_FAILED', 'USER', $u->id, 'wrong two-step code', $u->username);
        if ($p['tries'] >= 5) { session()->forget([TwoStep::PENDING, 'vis.otp.sent']); return redirect('/login?error=otp'); }
        session([TwoStep::PENDING => $p]);
        return view('auth.otp', ['u' => $u, 'error' => t('otp.error.code'), 'masked' => $this->mask($u->mobile)]);
    }

    public function resend()
    {
        $u = $this->pendingUser();
        if (!$u || $u->otp_method === 'app' || !$u->otp_method) return redirect('/login/otp');
        $err = $this->two->sendCode($u, $u->otp_method);
        return view('auth.otp', ['u' => $u, 'error' => $err ? t($err) : null, 'info' => $err ? null : t('otp.resent'), 'masked' => $this->mask($u->mobile)]);
    }

    // ------------------------------------------------------------------ set-up (forced at login, or from the account page)

    public function setup()
    {
        $u = $this->me();
        if (!$u) return redirect('/login');
        $s = session(self::SETUP);
        return view('auth.otp-setup', ['u' => $u, 'methods' => $this->two->methods(), 'setup' => $s, 'pending' => !$this->user()->check(),
            'uri' => ($s['method'] ?? null) === 'app' ? TwoStep::otpauthUri($s['secret'], $u->username) : null]);
    }

    public function setupStart(Request $r)
    {
        $u = $this->me();
        if (!$u) return redirect('/login');
        $method = in_array($r->input('method'), $this->two->methods(), true) ? $r->input('method') : 'app';
        $s = ['method' => $method];
        if ($method === 'app') $s['secret'] = TwoStep::newSecret();
        else {
            $mobile = preg_replace('/\D/', '', (string) $r->input('mobile', $u->mobile));
            $mobile = preg_replace('/^(91|0)(?=\d{10}$)/', '', $mobile);
            if (!preg_match('/^[6-9]\d{9}$/', $mobile)) return back()->with('error', 'validation.mobile');
            $tmp = clone $u; $tmp->mobile = $mobile;
            if ($err = $this->two->sendCode($tmp, $method)) return back()->with('error', $err);
            $s['mobile'] = $mobile;
        }
        session([self::SETUP => $s]);
        return redirect($this->user()->check() ? '/account/security/setup' : '/login/otp/setup');
    }

    public function setupConfirm(Request $r)
    {
        $u = $this->me();
        $s = session(self::SETUP);
        if (!$u || !$s) return redirect($this->user()->check() ? '/account/security' : '/login');
        $code = preg_replace('/\D/', '', (string) $r->input('code'));
        $ok = $s['method'] === 'app' ? $this->two->checkTotp($u->id, $s['secret'], $code) : $this->two->checkCode($u, $code);
        if (!$ok) return back()->with('error', 'otp.error.code');
        $u->otp_method = $s['method'];
        $u->otp_secret = $s['method'] === 'app' ? Crypt::encryptString($s['secret']) : null;
        if (isset($s['mobile'])) $u->mobile = $s['mobile'];
        $u->otp_since = now();
        $u->save();
        $codes = TwoStep::newRecovery($u);
        session()->forget(self::SETUP);
        $this->audit()->log('OTP_ON', 'USER', $u->id, $u->otp_method, $u->username);
        session()->flash(self::CODES, $codes);
        if (!$this->user()->check() && ($p = $this->pending())) $this->login->finish($p['payload'], $u->id, 'two-step set up: ' . $u->otp_method);
        return redirect('/account/security');
    }

    // ------------------------------------------------------------------ account page

    public function security()
    {
        $u = $this->me();
        return view('account.security', ['u' => $u, 'codes' => session(self::CODES), 'methods' => $this->two->methods(),
            'requiredByPolicy' => $u && $this->two->required(tap(clone $u, fn ($x) => $x->otp_method = null), $this->user()->permissions()),
            'left' => count(json_decode((string) $u?->otp_recovery, true) ?: [])]);
    }

    public function disable(Request $r)
    {
        $u = $this->me();
        if (!$u || !AppUser::checkPassword((string) $r->input('password'), $u->password_hash)) return back()->with('error', 'account.error.current');
        if ($this->two->required(tap(clone $u, fn ($x) => $x->otp_method = null), $this->user()->permissions())) return back()->with('error', 'otp.error.required');
        $u->update(['otp_method' => null, 'otp_secret' => null, 'otp_recovery' => null, 'otp_since' => null]);
        $this->audit()->log('OTP_OFF', 'USER', $u->id, null, $u->username);
        return redirect('/account/security')->with('success', 'otp.off');
    }

    public function recovery(Request $r)
    {
        $u = $this->me();
        if (!$u || !$u->otp_method || !AppUser::checkPassword((string) $r->input('password'), $u->password_hash)) return back()->with('error', 'account.error.current');
        session()->flash(self::CODES, TwoStep::newRecovery($u));
        $this->audit()->log('OTP_CODES', 'USER', $u->id, 'new recovery codes', $u->username);
        return redirect('/account/security');
    }

    /** Admin: lost phone / left the job. The user sets two-step up again at the next login (when the policy asks for it). */
    public function adminReset(int $id)
    {
        $u = AppUser::findOrFail($id);
        $u->update(['otp_method' => null, 'otp_secret' => null, 'otp_recovery' => null, 'otp_since' => null]);
        $this->audit()->log('OTP_RESET', 'USER', $u->id, 'by admin', null);
        return redirect('/admin/users')->with('success', 'otp.resetDone');
    }
}

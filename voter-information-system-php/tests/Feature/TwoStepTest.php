<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Role;
use App\Services\Auth\TwoStep;
use App\Services\Messaging\Gateway;
use App\Services\Messaging\Providers\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        app(\App\Services\Auth\Defaults::class)->ensure(true);
        $u = AppUser::create(['username' => 'op', 'full_name' => 'Op', 'enabled' => true, 'created_at' => now(), 'mobile' => '9811122233',
            'password_hash' => AppUser::hashPassword('secret@123')]);
        $u->roles()->attach(Role::where('code', 'OPERATOR')->value('id'));
    }

    private function login(string $u = 'op', string $p = 'secret@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    public function test_totp_matches_rfc_6238_vector(): void
    {
        // RFC 6238 test secret "12345678901234567890" (base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ), T = 59 s -> 94287082 (8 digits) -> last 6 = 287082
        $this->assertSame('287082', TwoStep::totp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(59, 30)));
        $this->assertSame(32, strlen(TwoStep::newSecret()));
    }

    public function test_authenticator_app_setup_login_trust_and_recovery(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);   // this test sends more codes than the per-minute limit
        $this->login()->assertRedirect('/dashboard');   // off by default
        $this->get('/account/security')->assertOk()->assertSee(t('otp.turnOn'));
        $this->post('/account/security/setup', ['method' => 'app'])->assertRedirect('/account/security/setup');
        $secret = session('vis.otp.setup')['secret'];
        $this->get('/account/security/setup')->assertOk()->assertSee('otpauth://totp/', false);
        $this->post('/account/security/confirm', ['code' => '000000'])->assertRedirect();
        $this->assertNull(AppUser::where('username', 'op')->value('otp_method'));
        $step = intdiv(time(), 30);
        $this->post('/account/security/confirm', ['code' => TwoStep::totp($secret, $step)])->assertRedirect('/account/security');
        $codes = session('vis.otp.codes');
        $this->assertCount(8, $codes);
        $this->assertSame('app', AppUser::where('username', 'op')->value('otp_method'));
        $this->assertStringNotContainsString($secret, (string) AppUser::where('username', 'op')->value('otp_secret'));   // stored encrypted
        $this->post('/logout');

        // password alone is not enough now
        $this->login()->assertRedirect('/login/otp');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login/otp', ['code' => '111111'])->assertOk()->assertSee(t('otp.error.code'));
        $this->post('/login/otp', ['code' => TwoStep::totp($secret, $step)])->assertOk();   // same code again: replay refused
        $res = $this->post('/login/otp', ['code' => TwoStep::totp($secret, $step + 1), 'trust' => '1'])->assertRedirect('/dashboard');
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), TwoStep::COOKIE));
        $this->assertNotNull($cookie);
        $this->post('/logout');

        // trusted device: no code asked
        $plain = $res->getCookie($cookie->getName())->getValue();   // as the browser sends it back (the test client encrypts it)
        $this->withCookie($cookie->getName(), $plain)->post('/login', ['username' => 'op', 'password' => 'secret@123'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->flushSession();

        // recovery code works once
        $this->defaultCookies = [];   // another device
        $this->login()->assertRedirect('/login/otp');
        $this->post('/login/otp', ['code' => strtolower($codes[0])])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->login()->assertRedirect('/login/otp');
        $this->post('/login/otp', ['code' => $codes[0]])->assertOk()->assertSee(t('otp.error.code'));
    }

    public function test_admins_must_set_it_up_and_admin_can_reset(): void
    {
        config(['voters.security.otp_required' => 'admins']);
        $this->login()->assertRedirect('/dashboard');                    // operators: not required
        $this->post('/logout');
        $this->login('admin', 'admin@123')->assertRedirect('/login/otp/setup');
        $this->get('/admin/users')->assertRedirect('/login');            // not logged in until set up
        $this->get('/login/otp/setup')->assertOk()->assertSee(t('otp.setupRequired'));
        $this->post('/login/otp/setup', ['method' => 'app']);
        $secret = session('vis.otp.setup')['secret'];
        $this->post('/login/otp/confirm', ['code' => TwoStep::totp($secret, intdiv(time(), 30))])->assertRedirect('/account/security');
        $this->get('/admin/users')->assertOk()->assertSee(t('otp.method.app'));
        $this->get('/account/security')->assertDontSee(t('otp.turnOffBtn'));   // required: cannot switch off
        $op = AppUser::where('username', 'op')->first();
        $op->update(['otp_method' => 'app', 'otp_secret' => \Illuminate\Support\Facades\Crypt::encryptString(TwoStep::newSecret())]);
        $this->post('/admin/users/' . $op->id . '/otp-reset')->assertRedirect('/admin/users');
        $this->assertNull($op->fresh()->otp_method);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_log')->where('action', 'OTP_RESET')->count());
    }

    public function test_sms_code_and_lockout_after_five_wrong_codes(): void
    {
        $sent = [];
        app()->instance(Gateway::class, new class($sent) extends Gateway {
            public function __construct(public array &$sent) {}
            public function ready(string $channel): bool { return true; }
            public function dryRun(string $channel): bool { return false; }
            public function send(string $channel, string $e164, string $body, ?\App\Models\MessageTemplate $t, array $params): SendResult
            { $this->sent[] = [$channel, $e164, $params['code']]; return SendResult::ok('x'); }
        });
        $this->login();
        $this->post('/account/security/setup', ['method' => 'sms', 'mobile' => '+91 98111 22233'])->assertRedirect('/account/security/setup');
        $this->assertSame(['SMS', '+919811122233'], [$sent[0][0], $sent[0][1]]);
        $this->post('/account/security/confirm', ['code' => $sent[0][2]])->assertRedirect('/account/security');
        $this->assertSame('sms', AppUser::where('username', 'op')->value('otp_method'));
        $this->post('/logout');
        $this->login()->assertRedirect('/login/otp');
        $this->get('/login/otp')->assertOk()->assertSee('2233');   // code sent to the masked mobile
        $this->assertCount(2, $sent);
        foreach (range(1, 4) as $i) $this->post('/login/otp', ['code' => '000000'])->assertOk();
        $this->post('/login/otp', ['code' => '000000'])->assertRedirect('/login?error=otp');
        $this->post('/login/otp', ['code' => $sent[1][2]])->assertRedirect('/login?error=expired');   // pending login gone
    }
}

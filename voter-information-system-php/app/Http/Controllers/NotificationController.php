<?php

namespace App\Http\Controllers;

use App\Models\Mandal;
use App\Models\Worker;
use App\Services\Notify\Notifier;
use App\Services\Voters\VoterService;
use App\Support\WorkerRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The bell: my notifications, open / mark read; Announcements: send a notification to a group of logins. */
class NotificationController extends Controller
{
    public function __construct(private Notifier $n) {}

    private function mine()
    {
        abort_unless(Notifier::enabled(), 404);
        return DB::table('notifications')->where('username', $this->user()->username());
    }

    public function index()
    {
        $page = $this->mine()->orderByDesc('id')->paginate(50);
        return view('notifications.index', ['page' => $page]);
    }

    /** Open one: marked read, then to its page (only links inside this site). */
    public function open(int $id)
    {
        $row = $this->mine()->where('id', $id)->first();
        abort_unless($row, 404);
        if (!$row->read_at) DB::table('notifications')->where('id', $id)->update(['read_at' => now()]);
        $url = (string) $row->url;
        return redirect($url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//') ? url($url) : url('/notifications'));
    }

    public function readAll()
    {
        $this->mine()->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', t('notif.allRead'));
    }

    /** Unread count for the bell (checked every minute while a page is open). */
    public function count()
    {
        return response()->json(['unread' => Notifier::enabled() ? $this->n->unread((string) $this->user()->username()) : 0])->header('Cache-Control', 'no-store');
    }

    // ------------------------------------------------------------------ announcements
    private function limits(): array
    {
        $a = $this->user()->scoped() ? $this->user()->workAssembly() : null;
        $booths = \App\Support\Area::current()['booths'] ?? [];
        return [$a, array_map('intval', $booths)];
    }

    public function sendForm(VoterService $voters)
    {
        abort_unless(Notifier::enabled(), 404);
        [$a] = $this->limits();
        $loginRoles = DB::table('roles')->orderBy('name')->pluck('name', 'code')->all();
        $sent = DB::table('notifications')->where('kind', 'ANNOUNCE')->where('created_by', $this->user()->username())
            ->selectRaw('title, body, created_at, count(*) as n, sum(case when read_at is null then 0 else 1 end) as seen')
            ->groupBy('title', 'body', 'created_at')->orderByDesc('created_at')->limit(20)->get();
        return view('notifications.send', ['assemblies' => $voters->assemblies(), 'mandals' => Mandal::where('active', true)->when($a, fn ($q) => $q->where('assembly_no', $a))->orderBy('assembly_no')->orderBy('name')->get(),
            'loginRoles' => $loginRoles, 'workerRoles' => WorkerRoles::codes(true), 'sent' => $sent, 'limitAssembly' => $a]);
    }

    public function send(Request $r)
    {
        abort_unless(Notifier::enabled(), 404);
        $d = $r->validate(['title' => 'required|string|max:200', 'body' => 'nullable|string|max:1000', 'url' => 'nullable|string|max:300',
            'target' => 'required|in:all,loginrole,workerrole', 'role' => 'nullable|string|max:40', 'assembly' => 'nullable|integer|min:1',
            'mandal' => 'nullable|integer', 'booth' => 'nullable|integer|min:1', 'preview' => 'nullable']);
        [$a, $booths] = $this->limits();
        if (!empty($d['url'])) {   // a page of this site only
            $path = parse_url($d['url'], PHP_URL_PATH) ?: '';
            $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
            if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
            $q = parse_url($d['url'], PHP_URL_QUERY);
            $d['url'] = '/' . ltrim($path, '/') . ($q ? '?' . $q : '');
        }
        $to = $this->n->recipients($d, $a, $booths)->reject(fn ($u) => $u === $this->user()->username());
        if ($r->has('preview')) return back()->withInput()->with('success', t('notif.willReach', $to->count()));
        if ($to->isEmpty()) return back()->withInput()->with('error', t('notif.nobody'));
        $n = $this->n->send($to, 'ANNOUNCE', trim($d['title']), trim((string) ($d['body'] ?? '')) ?: null, $d['url'] ?? null, $this->user()->username());
        $this->audit()->log('CREATE', 'NOTIFICATION', null, "announcement '{$d['title']}' to $n logins ({$d['target']}" . (!empty($d['role']) ? " {$d['role']}" : '') . ')');
        return redirect('/notifications/send')->with('success', t('notif.sent', $n));
    }
}

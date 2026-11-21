<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Models\Voter;
use App\Services\Messaging\GreetingService;
use App\Services\Messaging\InboxService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Messages > Inbox (two-way WhatsApp / SMS) and Messages > Automatic greetings. */
class OutreachController extends Controller
{
    public function __construct(private InboxService $inbox, private GreetingService $greet, private VoterService $voters) {}

    private function conv(int $id): object
    {
        $c = DB::table('conversations')->find($id);
        abort_if(!$c || ($c->assembly_no && !$this->user()->allows($c->assembly_no)) || (!$c->assembly_no && $this->user()->scoped()), 404, t('error.notFound'));
        return $c;
    }

    // ---------------------------------------------------------------- inbox

    public function inbox(Request $r, ?int $id = null)
    {
        $status = in_array($r->query('status'), ['OPEN', 'CLOSED', 'ALL'], true) ? $r->query('status') : 'OPEN';
        $q = trim((string) $r->query('q'));
        $list = DB::table('conversations')
            ->when($this->user()->scoped(), fn ($x) => $x->where('assembly_no', $this->user()->workAssembly()))
            ->when($status !== 'ALL', fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('mobile', 'like', '%' . preg_replace('/\D/', '', $q) . '%')->orWhere('name', 'like', '%' . $q . '%')))
            ->orderByDesc('last_at')->limit(200)->get();
        $conv = $id ? $this->conv($id) : null;
        $messages = collect(); $templates = collect(); $voter = null;
        if ($conv) {
            if ($conv->unread) DB::table('conversations')->where('id', $conv->id)->update(['unread' => 0]);
            $messages = DB::table('conversation_messages')->where('conversation_id', $conv->id)->orderByDesc('id')->limit(200)->get()->reverse()->values();
            $templates = MessageTemplate::where('channel', $conv->channel)->where('active', true)->orderBy('name')->get();
            $voter = $conv->voter_id ? Voter::find($conv->voter_id) : null;
        }
        return view('messages.inbox', ['list' => $list, 'conv' => $conv, 'messages' => $messages, 'templates' => $templates, 'voter' => $voter,
            'status' => $status, 'q' => $q, 'windowOpen' => $conv ? $this->inbox->windowOpen($conv) : true,
            'hooksOn' => strlen((string) config('voters.messaging.webhook_token')) >= 16]);
    }

    public function reply(Request $r, int $id)
    {
        $conv = $this->conv($id);
        $tpl = $r->input('template_id') && ($r->input('useTemplate') || !$r->filled('body')) ? MessageTemplate::find((int) $r->input('template_id')) : null;
        $err = $this->inbox->reply($conv, $r->input('body'), $tpl, $this->user()->username());
        $this->audit()->log('MSG_REPLY', 'CONVERSATION', $conv->id, $err ? 'failed: ' . $err : 'reply sent');
        return redirect('/messages/inbox/' . $conv->id)->with($err ? 'error' : 'success', $err ?: t('inbox.sent'))->withInput($err ? $r->only('body') : []);
    }

    public function convStatus(Request $r, int $id)
    {
        $conv = $this->conv($id);
        $upd = ['status' => $r->input('status') === 'CLOSED' ? 'CLOSED' : 'OPEN'];
        if ($r->has('link') && ($v = Voter::find((int) $r->input('link'))) && $this->user()->allowsVoter($v)) {
            $upd += ['voter_id' => $v->id, 'name' => $v->name, 'assembly_no' => $v->assembly_no];
            unset($upd['status']);
        }
        DB::table('conversations')->where('id', $conv->id)->update($upd);
        return redirect('/messages/inbox/' . $conv->id);
    }

    // ---------------------------------------------------------------- automatic greetings

    public function greetings()
    {
        $rules = DB::table('auto_greetings')->when($this->user()->scoped(), fn ($q) => $q->where('assembly_no', $this->user()->workAssembly()))->orderBy('kind')->get();
        $up = [];
        foreach (array_keys(GreetingService::KINDS) as $k) $up[$k] = $this->greet->upcoming($k, $this->user()->workAssembly(), 7);
        return view('messages.greetings', ['rules' => $rules, 'templates' => MessageTemplate::where('active', true)->orderBy('channel')->orderBy('name')->get()->keyBy('id'),
            'upcoming' => $up, 'assemblies' => $this->voters->assemblies(), 'silence' => app(\App\Services\Messaging\MessagingService::class)->silencePeriod()]);
    }

    public function saveGreeting(Request $r)
    {
        $d = $r->validate(['kind' => 'required|in:' . implode(',', array_keys(GreetingService::KINDS)), 'template_id' => 'required|integer|exists:message_templates,id',
            'assembly_no' => 'nullable|integer', 'send_at' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/']]);
        $a = $this->user()->scoped() ? $this->user()->workAssembly() : ($d['assembly_no'] ?? null);
        $row = ['kind' => $d['kind'], 'template_id' => $d['template_id'], 'assembly_no' => $a, 'send_at' => $d['send_at'], 'active' => true];
        if ($r->input('id')) {
            $old = DB::table('auto_greetings')->find((int) $r->input('id'));
            abort_if(!$old || ($old->assembly_no && !$this->user()->allows($old->assembly_no)), 404);
            DB::table('auto_greetings')->where('id', $old->id)->update($row);
        } else {
            DB::table('auto_greetings')->insert($row + ['created_by' => $this->user()->username(), 'created_at' => now()]);
        }
        $this->audit()->log('UPDATE', 'GREETING', null, $d['kind'] . ' greetings with template ' . $d['template_id']);
        return redirect('/messages/greetings')->with('success', t('greet.saved'));
    }

    public function toggleGreeting(Request $r, int $id)
    {
        $g = DB::table('auto_greetings')->find($id);
        abort_if(!$g || ($g->assembly_no && !$this->user()->allows($g->assembly_no)) || (!$g->assembly_no && $this->user()->scoped()), 404);
        if ($r->input('delete')) DB::table('auto_greetings')->where('id', $id)->delete();
        else DB::table('auto_greetings')->where('id', $id)->update(['active' => !$g->active]);
        $this->audit()->log('UPDATE', 'GREETING', $id, $r->input('delete') ? 'deleted' : ($g->active ? 'paused' : 'resumed'));
        return redirect('/messages/greetings')->with('success', t('greet.saved'));
    }
}

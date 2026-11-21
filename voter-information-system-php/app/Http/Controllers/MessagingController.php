<?php

namespace App\Http\Controllers;

use App\Models\{Campaign, MessageTemplate, OptOut, OutboxMessage};
use App\Services\Messaging\{Gateway, MessagingService, TemplateRenderer};
use App\Services\Voters\VoterFilter;
use App\Support\Lang;
use App\Support\Validation;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    public function __construct(private MessagingService $service, private Gateway $gateway) {}

    private function common(): array
    {
        return ['channels' => MessageTemplate::CHANNELS, 'smsDryRun' => $this->gateway->dryRun('SMS'), 'waDryRun' => $this->gateway->dryRun('WHATSAPP'),
            'silence' => $this->service->silencePeriod()];
    }

    private function filter(Request $r): VoterFilter
    {
        $f = VoterFilter::fromRequest($r);
        $f->set('assemblyNo', $this->user()->restrict($f->get('assemblyNo')));
        return $f;
    }

    public function compose(Request $r)
    {
        $f = $this->filter($r);
        $channel = $r->query('channel') === 'WHATSAPP' ? 'WHATSAPP' : 'SMS';
        $list = MessageTemplate::where('channel', $channel)->where('active', true)->orderBy('language')->orderBy('name')->get();
        $t = $r->query('templateId') ? $list->firstWhere('id', (int) $r->query('templateId')) : null;
        $t ??= $list->firstWhere('language', Lang::current()) ?? $list->first();
        return view('messages.compose', $this->common() + ['f' => $f, 'channel' => $channel, 'templates' => $list, 'template' => $t,
            'ready' => $this->gateway->ready($channel), 'dryRun' => $this->gateway->dryRun($channel), 'preview' => $t ? $this->service->preview($f, $t) : null]);
    }

    public function create(Request $r)
    {
        $f = $this->filter($r);
        $t = MessageTemplate::findOrFail($r->input('templateId'));
        try {
            $c = $this->service->create($r->input('campaignName'), $t, $f, VoterController::summary($f), $r->input('scheduledAt'), $this->user()->username());
            return redirect('/messages/campaigns/' . $c->id)->with('success', 'msg.flash.queued');
        } catch (\RuntimeException $e) {
            return redirect('/messages/compose?' . $f->queryWithSort() . "&channel={$t->channel}&templateId={$t->id}")->with('error', $e->getMessage());
        }
    }

    public function campaigns()
    {
        return view('messages.campaigns', $this->common() + ['page' => Campaign::with('template')->orderByDesc('id')->paginate(25)]);
    }

    public function campaign(Request $r, int $id)
    {
        $c = Campaign::with('template')->findOrFail($id);
        $status = in_array($r->query('status'), OutboxMessage::STATUSES, true) ? $r->query('status') : null;
        $page = OutboxMessage::where('campaign_id', $id)->when($status, fn ($q) => $q->where('status', $status))->orderBy('id')->paginate(50)->withQueryString();
        $delivery = \Illuminate\Support\Facades\Schema::hasColumn('message_outbox', 'delivery')
            ? OutboxMessage::where('campaign_id', $id)->whereNotNull('delivery')->selectRaw('delivery, count(*) as n')->groupBy('delivery')->pluck('n', 'delivery')->all() : null;
        return view('messages.campaign', $this->common() + ['c' => $c, 'status' => $status, 'statuses' => OutboxMessage::STATUSES, 'page' => $page, 'delivery' => $delivery,
            'windowOpen' => $this->service->insideSendWindow(), 'sendFrom' => config('voters.messaging.send_from'), 'sendUntil' => config('voters.messaging.send_until')]);
    }

    public function cancel(int $id)
    {
        $this->service->cancel(Campaign::findOrFail($id), $this->user()->username());
        return redirect("/messages/campaigns/$id")->with('success', 'msg.flash.cancelled');
    }

    // ---------------------------------------------------------------- templates
    public function templates()
    {
        return view('messages.templates', $this->common() + ['items' => MessageTemplate::orderBy('channel')->orderBy('language')->orderBy('name')->get()]);
    }

    public function templateForm(?int $id = null)
    {
        return view('messages.template-form', $this->common() + ['item' => $id ? MessageTemplate::findOrFail($id) : new MessageTemplate(['active' => true, 'channel' => 'SMS', 'language' => 'en']),
            'placeholders' => array_keys(TemplateRenderer::placeholders())]);
    }

    public function saveTemplate(Request $r)
    {
        $item = $r->input('id') ? MessageTemplate::findOrFail($r->input('id')) : new MessageTemplate();
        $data = Validation::clean($r->only(['name', 'channel', 'language', 'body', 'provider_template_id', 'params']), []);
        $data['active'] = $r->boolean('active');
        $v = validator($data, ['name' => ['required', 'max:100'], 'channel' => ['required', 'in:SMS,WHATSAPP'], 'language' => ['required', 'max:10'],
            'body' => ['required', 'max:1000'], 'provider_template_id' => ['nullable', 'max:100'], 'params' => ['nullable', 'max:300']], Validation::messages());
        $v->after(function ($val) use ($data) {
            $bad = TemplateRenderer::unknownPlaceholders($data['body'] ?? '', $data['params'] ?? '');
            if ($bad) $val->errors()->add('body', t('msg.error.placeholder', implode(', ', $bad)));
        });
        if ($v->fails()) {
            $item->fill($data);
            return response()->view('messages.template-form', $this->common() + ['item' => $item, 'errors' => $v->errors(), 'placeholders' => array_keys(TemplateRenderer::placeholders())]);
        }
        $isNew = !$item->exists;
        $item->fill($data)->save();
        $this->audit()->log($isNew ? 'CREATE' : 'UPDATE', 'TEMPLATE', $item->id, $item->channel . ' ' . $item->name);
        return redirect('/messages/templates')->with('success', 'flash.saved');
    }

    public function deleteTemplate(int $id)
    {
        $t = MessageTemplate::findOrFail($id);
        if (Campaign::where('template_id', $id)->exists()) { $t->update(['active' => false]); $flash = 'msg.flash.deactivated'; }
        else { $t->delete(); $flash = 'flash.deleted'; }
        $this->audit()->log('DELETE', 'TEMPLATE', $id, $t->name);
        return redirect('/messages/templates')->with('success', $flash);
    }

    // ---------------------------------------------------------------- opt-outs
    public function optOuts() { return view('messages.optouts', $this->common() + ['items' => OptOut::orderByDesc('created_at')->get()]); }

    public function addOptOut(Request $r)
    {
        $ok = $this->service->optOut($r->input('mobile'), $r->input('channel') ?: null, $r->input('reason'), $this->user()->username());
        $back = (string) $r->input('back');
        return redirect(str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : '/messages/optouts')
            ->with($ok ? 'success' : 'error', $ok ? 'msg.flash.optedOut' : 'validation.mobile');
    }

    public function deleteOptOut(int $id)
    {
        if ($o = OptOut::find($id)) { $o->delete(); $this->audit()->log('DELETE', 'OPTOUT', $id); }
        return redirect('/messages/optouts')->with('success', 'flash.deleted');
    }

    // ---------------------------------------------------------------- settings
    public function settings()
    {
        return view('messages.settings', $this->common() + ['cfg' => config('voters.messaging'), 'smsReady' => $this->gateway->ready('SMS'),
            'waReady' => $this->gateway->ready('WHATSAPP'), 'windowOpen' => $this->service->insideSendWindow(),
            'templatesSms' => MessageTemplate::where('channel', 'SMS')->where('active', true)->get(),
            'templatesWa' => MessageTemplate::where('channel', 'WHATSAPP')->where('active', true)->get()]);
    }

    public function test(Request $r)
    {
        $t = MessageTemplate::findOrFail($r->input('templateId'));
        $e = $this->service->e164($r->input('mobile'));
        if (!$e) return redirect('/messages/settings')->with('error', 'validation.mobile');
        if ($this->service->silencePeriod()) return redirect('/messages/settings')->with('error', 'msg.error.silence');
        $res = $this->gateway->send($t->channel, $e, $t->body, $t, []);
        $this->audit()->log('MSG_TEST', 'TEMPLATE', $t->id, MessagingService::mask($e) . ' ' . ($res->success ? 'ok ' . $res->providerId : $res->error));
        return redirect('/messages/settings')->with('testResult', $res->success ? t('msg.test.ok') . ' ' . $res->providerId : t('msg.test.failed') . ' ' . $res->error);
    }
}

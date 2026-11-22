<?php

namespace App\Http\Controllers;

use App\Support\Permission;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Admin > Settings: features the administrator switches on or off for this installation. */
class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings', ['messaging' => Permission::messagingOn(), 'messagingSet' => Settings::get('messaging_enabled') !== null,
            'envDefault' => (bool) config('voters.messaging.enabled', true),
            'changedBy' => \Illuminate\Support\Facades\DB::table('app_settings')->where('name', 'messaging_enabled')->first()]);
    }

    public function save(Request $r)
    {
        $on = $r->input('messaging') === '1';
        $was = Permission::messagingOn();
        Settings::set('messaging_enabled', $on ? '1' : '0', $this->user()->username());
        if ($was !== $on) $this->audit()->log('UPDATE', 'SETTING', null, 'SMS / WhatsApp switched ' . ($on ? 'ON' : 'OFF'));
        return redirect('/admin/settings')->with('success', t($on ? 'settings.msgOnDone' : 'settings.msgOffDone'));
    }
}

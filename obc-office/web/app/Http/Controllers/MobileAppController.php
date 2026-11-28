<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Distributes the Android app:
 *  - Administration → Mobile App: upload new versions, release notes, public link on/off, QR code
 *  - /app: public install page (when enabled) with a direct download link
 */
class MobileAppController extends Controller
{
    private const DISK = 'local';

    private function hasApk(): bool
    {
        $path = setting('app_apk_path');

        return $path && Storage::disk(self::DISK)->exists($path);
    }

    private function viewData(): array
    {
        return [
            'hasApk' => $this->hasApk(),
            'version' => setting('app_version'),
            'notes' => setting('app_notes'),
            'sizeMb' => setting('app_size') ? round(setting('app_size') / 1048576, 1) : null,
            'uploadedAt' => setting('app_uploaded_at'),
            'downloads' => (int) setting('app_downloads', 0),
            'isPublic' => (bool) setting('app_public'),
            'publicUrl' => route('app.public'),
            'downloadUrl' => route('app.download'),
        ];
    }

    /** Administration → Mobile App */
    public function index(Request $request)
    {
        abort_unless($request->user()->canView('mobile_app'), 403);

        return view('mobile-app.index', $this->viewData() + ['canManage' => $request->user()->canManage('mobile_app')]);
    }

    public function upload(Request $request)
    {
        abort_unless($request->user()->canManage('mobile_app'), 403);
        $data = $request->validate([
            'apk' => 'required|file|extensions:apk|max:153600',
            'version' => 'required|string|max:20',
            'notes' => 'nullable|string|max:2000',
        ]);

        $old = setting('app_apk_path');
        $path = $request->file('apk')->storeAs('mobile-app', 'obc-office-'.preg_replace('/[^0-9A-Za-z.\-]/', '', $data['version']).'.apk', self::DISK);
        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }

        Setting::put('app_apk_path', $path);
        Setting::put('app_version', $data['version']);
        Setting::put('app_notes', $data['notes'] ?? '');
        Setting::put('app_size', $request->file('apk')->getSize());
        Setting::put('app_uploaded_at', now()->toDateTimeString());
        AuditLog::record('app_upload', null, 'version '.$data['version']);

        return redirect()->route('mobile-app.index')->with('ok', "Version {$data['version']} is now available for download.");
    }

    public function saveSettings(Request $request)
    {
        abort_unless($request->user()->canManage('mobile_app'), 403);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        Setting::put('app_public', $request->boolean('app_public'));
        Setting::put('app_notes', $data['notes'] ?? '');
        AuditLog::record('settings', null, 'mobile app (public page '.($request->boolean('app_public') ? 'on' : 'off').')');

        return redirect()->route('mobile-app.index')->with('ok', 'Saved.');
    }

    /** Public install page: https://…/app */
    public function publicPage(Request $request)
    {
        if (! setting('app_public') && ! $request->user()?->canView('mobile_app')) {
            abort(404);
        }

        return view('mobile-app.public', $this->viewData());
    }

    /** Direct APK download (public when the install page is enabled, otherwise signed-in users only). */
    public function download(Request $request)
    {
        if (! setting('app_public') && ! $request->user()?->canView('mobile_app')) {
            abort(404);
        }
        abort_unless($this->hasApk(), 404, 'The app has not been uploaded yet.');

        Setting::put('app_downloads', (int) setting('app_downloads', 0) + 1);
        $name = 'OBC-Office'.(setting('app_version') ? '-'.setting('app_version') : '').'.apk';

        return Storage::disk(self::DISK)->download(setting('app_apk_path'), $name, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache',
        ]);
    }
}

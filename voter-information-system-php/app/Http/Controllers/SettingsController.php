<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin > Settings: everything the administrator may change for this installation, without touching .env. */
class SettingsController extends Controller
{
    public const GROUPS = ['offline', 'login', 'display', 'smart', 'planner', 'messaging'];

    public function index()
    {
        $rows = DB::table('app_settings')->get()->keyBy('name');
        $items = [];
        foreach (Settings::SPEC as $name => [$group, $key, $type, $rule]) {
            $items[$group][$name] = ['type' => $type, 'rule' => $rule, 'value' => config($key), 'default' => Settings::envDefault($name),
                'set' => isset($rows[$name]), 'by' => $rows[$name]->updated_by ?? null, 'at' => isset($rows[$name]) ? substr((string) $rows[$name]->updated_at, 0, 16) : null];
        }
        return view('admin.settings', ['groups' => self::GROUPS, 'items' => $items]);
    }

    public function save(Request $r)
    {
        $changed = [];
        foreach (Settings::SPEC as $name => [$group, $key, $type, $rule]) {
            if ($r->input('reset') === $name) { Settings::forget($name); $changed[] = "$name reset"; continue; }
            if ($type === 'image') {
                if (!$r->hasFile("photo.$name")) continue;
                $path = self::storeImage($r->file("photo.$name"), $name);
                if ($path === null) return redirect('/admin/settings')->with('error', t('settings.photoBad'));
                Settings::set($name, $path, $this->user()->username());
                $changed[] = "$name uploaded";
                continue;
            }
            if (!$r->has("s.$name") && $type !== 'bool') continue;
            $raw = $r->input("s.$name");
            $val = match ($type) {
                'bool' => $r->input("s.$name") === '1' ? '1' : ($r->has("s.$name") ? '0' : null),
                'int' => is_numeric($raw) ? (string) max($rule[0], min($rule[1], (int) $raw)) : null,
                'enum' => in_array($raw, $rule, true) ? $raw : null,
                'text' => mb_substr(trim((string) $raw), 0, $rule),
            };
            if ($val === null) continue;
            $now = config($key);
            $nowStr = is_bool($now) ? ($now ? '1' : '0') : (string) $now;
            if ($nowStr === $val) continue;
            Settings::set($name, $val, $this->user()->username());
            $changed[] = "$name = " . ($type === 'text' ? '"' . $val . '"' : $val);
        }
        if ($changed) $this->audit()->log('UPDATE', 'SETTING', null, implode('; ', $changed));
        return redirect('/admin/settings')->with('success', $changed ? t('settings.savedN', count($changed)) : t('settings.nothing'));
    }

    /**
     * Uploaded photo -> square JPEG (cropped to the centre, 400 x 400) in public/uploads/branding/.
     * Re-drawing it keeps only the picture (nothing else that may be inside the file). Returns the path under public/, or null.
     */
    public static function storeImage(\Illuminate\Http\UploadedFile $f, string $name): ?string
    {
        if (!$f->isValid() || $f->getSize() > 5 * 1024 * 1024 || !function_exists('imagecreatefromstring')) return null;
        $info = @getimagesize($f->getRealPath());
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
        $src = @imagecreatefromstring((string) file_get_contents($f->getRealPath()));
        if (!$src) return null;
        $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
        $out = imagecreatetruecolor(400, 400);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // transparent PNG: white behind
        imagecopyresampled($out, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 400, 400, $side, $side);
        $dir = public_path('uploads/branding');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;
        $file = 'uploads/branding/' . preg_replace('/[^a-z_]/', '', $name) . '-' . date('YmdHis') . '.jpg';
        $ok = imagejpeg($out, public_path($file), 85);
        imagedestroy($src); imagedestroy($out);
        return $ok ? $file : null;
    }
}

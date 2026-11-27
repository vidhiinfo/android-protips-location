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
                $path = $name === 'app_icon' ? self::storeAppIcon($r->file("photo.$name")) : self::storeImage($r->file("photo.$name"), $name);
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

    /** Paths of the home-screen icon sizes (512, 192, 180) made from one upload; null for the standard icon. */
    public static function appIcons(): ?array
    {
        $p = (string) config('voters.branding.app_icon');
        if ($p === '' || !is_file(public_path($p))) return null;
        return ['512' => $p, '192' => str_replace('-512.png', '-192.png', $p), '180' => str_replace('-512.png', '-180.png', $p)];
    }

    /** Uploaded image -> square PNG icons 512, 192 and 180 (iPhone) in public/uploads/branding/. Returns the 512 path. */
    public static function storeAppIcon(\Illuminate\Http\UploadedFile $f): ?string
    {
        if (!$f->isValid() || $f->getSize() > 5 * 1024 * 1024 || !function_exists('imagecreatefromstring')) return null;
        $info = @getimagesize($f->getRealPath());
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
        $src = @imagecreatefromstring((string) file_get_contents($f->getRealPath()));
        if (!$src) return null;
        $dir = public_path('uploads/branding');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;
        $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
        $base = 'uploads/branding/app-icon-' . date('YmdHis');
        foreach ([512, 192, 180] as $size) {
            $out = imagecreatetruecolor($size, $size);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // icons need a solid background
            imagecopyresampled($out, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
            $ok = imagepng($out, public_path("$base-$size.png"), 9);
            imagedestroy($out);
            if (!$ok) { imagedestroy($src); return null; }
        }
        imagedestroy($src);
        return "$base-512.png";
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

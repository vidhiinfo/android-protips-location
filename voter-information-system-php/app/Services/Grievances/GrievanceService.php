<?php

namespace App\Services\Grievances;

use App\Models\Grievance;
use App\Services\Messaging\Gateway;
use App\Services\Messaging\MessagingService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Grievance deadlines (days per category, shorter for high / urgent), escalation of overdue grievances,
 * the timeline of updates, and photos (before / after), kept privately under storage/app/grievances.
 */
class GrievanceService
{
    public const MAX_PHOTOS = 10;
    public const MAX_KB = 8192;
    private const MAX_SIDE = 1600;

    public function __construct(private Gateway $gateway, private MessagingService $msg) {}

    public static function enabled(): bool { return Schema::hasTable('grievance_sla'); }

    /** [category => ['days' =>, 'escalate_to' =>, 'escalate_to2' =>]] */
    public static function sla(): array
    {
        if (!self::enabled()) return [];
        return DB::table('grievance_sla')->get()->keyBy('category')->map(fn ($r) => (array) $r)->all();
    }

    /** Due date: category days from the start; HIGH = half, URGENT = a third (at least one day). */
    public static function dueAt(string $category, string $priority, Carbon $from): ?Carbon
    {
        $days = self::sla()[$category]['days'] ?? null;
        if (!$days) return null;
        $days = match ($priority) { 'URGENT' => (int) ceil($days / 3), 'HIGH' => (int) ceil($days / 2), default => $days };
        return $from->copy()->addDays(max(1, $days));
    }

    public function note(int $grievanceId, string $kind, ?string $text, ?string $by): void
    {
        if (!Schema::hasTable('grievance_updates')) return;
        DB::table('grievance_updates')->insert(['grievance_id' => $grievanceId, 'kind' => $kind, 'text' => $text !== null ? mb_substr($text, 0, 1000) : null,
            'by_user' => $by, 'at' => now()]);
    }

    /**
     * Raises open grievances past their due date: level 1 when overdue, level 2 when overdue by the whole allowed time again.
     * Each step is written to the timeline and the login named in Deadlines is told by SMS / WhatsApp (GRIEVANCE_ALERT_CHANNEL).
     * Returns the number of grievances escalated. Runs at most every 30 minutes from page visits, or from cron.
     */
    public function escalate(bool $force = false): int
    {
        if (!self::enabled() || !Schema::hasColumn('grievances', 'due_at')) return 0;
        if (!$force) {
            if (Cache::get('vis.grv.escalate')) return 0;
            Cache::put('vis.grv.escalate', true, 1800);
        }
        $sla = self::sla(); $n = 0;
        foreach (Grievance::whereIn('status', ['OPEN', 'IN_PROGRESS'])->whereNotNull('due_at')->where('due_at', '<', now())->where('escalation_level', '<', 2)->orderBy('due_at')->limit(500)->get() as $g) {
            $allowed = max(1, (int) round($g->created_at ? $g->created_at->diffInHours($g->due_at, true) : 24));
            $level = now()->gte($g->due_at->copy()->addHours($allowed)) ? 2 : 1;
            if ($level <= $g->escalation_level) continue;
            $g->escalation_level = $level;
            $g->escalated_at = now();
            if ($level === 2 && $g->priority !== 'URGENT') $g->priority = $g->priority === 'HIGH' ? 'URGENT' : 'HIGH';
            $g->save();
            $to = $sla[$g->category][$level === 2 ? 'escalate_to2' : 'escalate_to'] ?? null;
            $this->note($g->id, 'ESCALATED', t('grv.escalatedNote', $level, $g->due_at->format('d-m-Y')) . ($to ? ' → ' . $to : ''), 'system');
            if ($to) $this->alert($to, $g, $level);
            $n++;
        }
        return $n;
    }

    private function alert(string $username, Grievance $g, int $level): void
    {
        $channel = strtolower((string) config('voters.grievance.alert_channel'));
        if (!in_array($channel, ['sms', 'whatsapp'], true) || !\App\Support\Permission::messagingOn()) return;
        $mobile = Schema::hasColumn('app_users', 'mobile') ? DB::table('app_users')->where('username', $username)->value('mobile') : null;
        $e164 = $this->msg->e164($mobile);
        if (!$e164) return;
        $body = t('grv.alertText', $g->id, t('gr.cat.' . $g->category), mb_substr($g->subject, 0, 60), $g->booth_no ?: '-', $level);
        $r = $this->gateway->send($channel === 'whatsapp' ? 'WHATSAPP' : 'SMS', $e164, $body, null, []);
        if (!$r->success) Log::warning('Grievance alert not sent: ' . $r->error);
    }

    /** Stores an uploaded photo (re-encoded as JPEG, longest side 1600 px, location data removed). Returns the photo id. */
    public function addPhoto(Grievance $g, UploadedFile $file, string $stage, ?string $caption, ?string $by): int
    {
        $dir = storage_path('app/grievances/' . $g->id);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = Str::random(24) . '.jpg';
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (!$src) throw new \InvalidArgumentException('not an image');
        // phone photos: turn upright using the camera's orientation flag
        if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $src = match ((int) $o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        imagejpeg($dst, $dir . '/' . $name, 82);   // re-encoding drops EXIF (GPS, phone model)
        imagedestroy($src); imagedestroy($dst);
        $id = DB::table('grievance_photos')->insertGetId(['grievance_id' => $g->id, 'stage' => $stage === 'AFTER' ? 'AFTER' : 'BEFORE',
            'path' => $g->id . '/' . $name, 'mime' => 'image/jpeg', 'bytes' => filesize($dir . '/' . $name),
            'caption' => $caption ? mb_substr($caption, 0, 200) : null, 'by_user' => $by, 'at' => now()]);
        $this->note($g->id, 'PHOTO', t('grv.photoNote.' . ($stage === 'AFTER' ? 'AFTER' : 'BEFORE')) . ($caption ? ': ' . $caption : ''), $by);
        return $id;
    }

    public static function photoPath(object $p): string { return storage_path('app/grievances/' . $p->path); }

    public function deletePhoto(object $p): void
    {
        @unlink(self::photoPath($p));
        DB::table('grievance_photos')->where('id', $p->id)->delete();
    }
}

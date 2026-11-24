<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stores photos on the private disk, downscaled (GD) to keep storage and mobile data small. */
class PhotoService
{
    public static function store(UploadedFile $file, string $dir): string
    {
        $max = (int) setting('photo_max_px', 600);
        $path = $dir.'/'.Str::uuid().'.jpg';
        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));

        if (! $src) {
            return $file->store($dir, 'local');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, 82);
        Storage::disk('local')->put($path, ob_get_clean());
        imagedestroy($src);
        imagedestroy($dst);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}

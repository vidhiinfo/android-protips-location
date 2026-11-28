<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Serves the logo and the leader's photo straight from storage, so they show on the
 * (public) login page without needing `php artisan storage:link` on shared hosting.
 */
class BrandingController extends Controller
{
    public function __invoke(string $type)
    {
        $path = setting(['leader' => 'leader_photo_path', 'login-bg' => 'login_bg_path'][$type] ?? 'logo_path');
        $disk = Storage::disk('public');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    }
}

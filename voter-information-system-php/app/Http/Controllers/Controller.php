<?php

namespace App\Http\Controllers;

use App\Services\Auth\AuditService;
use App\Services\Auth\CurrentUser;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    protected function user(): CurrentUser { return app(CurrentUser::class); }
    protected function audit(): AuditService { return app(AuditService::class); }

    protected function download(string $data, string $file, string $type): Response
    {
        return response($data, 200, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="' . $file . '"']);
    }

    protected const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
}

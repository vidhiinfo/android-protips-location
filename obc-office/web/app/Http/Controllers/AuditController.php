<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AuditController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('module:audit')];
    }

    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->query('entity')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'entities' => AuditLog::whereNotNull('entity')->distinct()->orderBy('entity')->pluck('entity'),
        ]);
    }
}

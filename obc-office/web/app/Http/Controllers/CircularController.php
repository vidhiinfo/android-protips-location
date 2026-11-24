<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Circular;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

class CircularController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('module:circulars'),
            new Middleware('module:circulars,manage', only: ['create', 'store', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $circulars = Circular::visibleTo($user)->with('targetState')->withCount('readers')
            ->with(['readers' => fn ($q) => $q->where('users.id', $user->id)])
            ->latest()->paginate(20);

        return view('circulars.index', ['circulars' => $circulars]);
    }

    public function create()
    {
        return view('circulars.form', ['states' => State::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:20000',
            'target_state_id' => 'nullable|exists:states,id',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);
        $c = new Circular($data);
        $c->created_by = $request->user()->id;
        if ($request->hasFile('attachment')) {
            $c->attachment_path = $request->file('attachment')->store('circulars', 'local');
        }
        $c->save();
        AuditLog::record('create', $c, $c->title);

        return redirect()->route('circulars.show', $c)->with('ok', 'Circular issued.');
    }

    public function show(Request $request, Circular $circular)
    {
        $user = $request->user();
        abort_unless(Circular::visibleTo($user)->whereKey($circular->id)->exists(), 403);
        $circular->readers()->syncWithoutDetaching([$user->id]);

        $readStats = null;
        if ($user->canManage('circulars')) {
            $audience = User::where('active', true)->whereIn('role', ['state_admin', 'district_admin'])
                ->when($circular->target_state_id, fn ($q) => $q->where('state_id', $circular->target_state_id))
                ->with(['state', 'district'])->orderBy('state_id')->get();
            $readIds = $circular->readers()->pluck('users.id')->all();
            $readStats = $audience->map(fn ($u) => ['user' => $u, 'read' => in_array($u->id, $readIds)]);
        }

        return view('circulars.show', ['circular' => $circular->load('targetState'), 'readStats' => $readStats]);
    }

    public function attachment(Request $request, Circular $circular)
    {
        abort_unless(Circular::visibleTo($request->user())->whereKey($circular->id)->exists(), 403);
        abort_unless($circular->attachment_path && Storage::disk('local')->exists($circular->attachment_path), 404);

        return Storage::disk('local')->download($circular->attachment_path);
    }

    public function destroy(Circular $circular)
    {
        AuditLog::record('delete', $circular, $circular->title);
        if ($circular->attachment_path) {
            Storage::disk('local')->delete($circular->attachment_path);
        }
        $circular->delete();

        return redirect()->route('circulars.index')->with('ok', 'Circular deleted.');
    }
}

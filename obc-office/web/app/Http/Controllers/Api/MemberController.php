<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canView('members') || $request->user()->canView('directory'), 403);
        $q = (new MemberService($request->user()))->query($request);
        if ($request->filled('updated_since')) { // incremental sync for offline use
            $q->where('updated_at', '>', $request->date('updated_since'));
        }

        return MemberResource::collection($q->orderBy('name')->paginate(min(200, (int) $request->query('per_page', 50))));
    }

    public function show(Request $request, Member $member)
    {
        abort_unless($request->user()->canView('members'), 403);
        (new MemberService($request->user()))->authorize($member);

        return new MemberResource($member->load(['state', 'district', 'designation']));
    }

    /** Multipart form: same fields as the web form, plus optional "photo" file (camera upload). */
    public function store(Request $request)
    {
        abort_unless($request->user()->canManage('members'), 403);
        $member = (new MemberService($request->user()))->save($request);

        return (new MemberResource($member->load(['state', 'district', 'designation'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Member $member)
    {
        abort_unless($request->user()->canManage('members'), 403);
        $svc = new MemberService($request->user());
        $svc->authorize($member);
        // Partial updates from the app: start from the current record, overlay the submitted fields.
        $request->merge(array_merge($member->only($member->getFillable()), [
            'dob' => $member->dob?->toDateString(), 'appointed_on' => $member->appointed_on?->toDateString(),
            'tenure_end' => $member->tenure_end?->toDateString(), 'consent' => $member->consent ? '1' : null,
            'extra' => $member->extra ?? [],
        ], $request->except('photo')));
        $svc->save($request, $member);

        return new MemberResource($member->fresh(['state', 'district', 'designation']));
    }

    public function photo(Request $request, Member $member)
    {
        abort_unless($request->user()->canView('members') || $request->user()->canView('directory'), 403);
        (new MemberService($request->user()))->authorize($member);
        abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

        return Storage::disk('local')->response($member->photo_path);
    }
}

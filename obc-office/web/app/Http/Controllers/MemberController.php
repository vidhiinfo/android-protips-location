<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CustomField;
use App\Models\Designation;
use App\Models\District;
use App\Models\Lookup;
use App\Models\Member;
use App\Models\State;
use App\Services\MemberService;
use App\Services\PhotoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('module:members', except: ['photo', 'directory']),
            new Middleware('module:members,manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
            new Middleware('module:directory', only: ['directory']),
        ];
    }

    private function svc(Request $request): MemberService
    {
        return new MemberService($request->user());
    }

    public function index(Request $request)
    {
        $members = $this->svc($request)->query($request)
            ->orderBy($request->query('sort') === 'recent' ? 'id' : 'name', $request->query('sort') === 'recent' ? 'desc' : 'asc')
            ->paginate((int) setting('per_page', 25))->withQueryString();

        return view('members.index', $this->formData($request) + [
            'members' => $members,
            'listFields' => CustomField::for('member')->where('show_in_list', true),
        ]);
    }

    public function create(Request $request)
    {
        $u = $request->user();
        $member = new Member([
            'type' => 'bearer', 'status' => 'active',
            'level' => $u->role === 'district_admin' ? 'district' : ($u->role === 'state_admin' ? 'state' : 'national'),
            'state_id' => $u->state_id, 'district_id' => $u->district_id,
        ]);

        return view('members.form', $this->formData($request) + ['member' => $member]);
    }

    public function store(Request $request)
    {
        $member = $this->svc($request)->save($request);

        return redirect()->route('members.show', $member)->with('ok', $member->status === 'proposed'
            ? 'Saved. The record is pending approval by the national office.' : 'Member saved.');
    }

    public function show(Request $request, Member $member)
    {
        $this->svc($request)->authorize($member);
        AuditLog::record('view', $member, $member->name);

        return view('members.show', [
            'member' => $member->load(['state', 'district', 'designation', 'creator']),
            'customFields' => CustomField::for('member'),
        ]);
    }

    public function edit(Request $request, Member $member)
    {
        $this->svc($request)->authorize($member);

        return view('members.form', $this->formData($request) + ['member' => $member]);
    }

    public function update(Request $request, Member $member)
    {
        $this->svc($request)->authorize($member);
        $this->svc($request)->save($request, $member);

        return redirect()->route('members.show', $member)->with('ok', 'Member updated.');
    }

    public function destroy(Request $request, Member $member)
    {
        $this->svc($request)->authorize($member);
        AuditLog::record('delete', $member, $member->name);
        $member->delete();

        return redirect()->route('members.index')->with('ok', 'Member removed.');
    }

    /** National office approves / rejects members proposed by states. */
    public function approve(Request $request, Member $member)
    {
        abort_unless($request->user()->isNational() && $request->user()->canManage('members'), 403);
        $status = $request->input('decision') === 'reject' ? 'removed' : 'active';
        $member->update(['status' => $status]);
        AuditLog::record($status === 'active' ? 'approve' : 'reject', $member, $member->name);

        return back()->with('ok', $status === 'active' ? 'Approved.' : 'Rejected.');
    }

    /** Photos are private: streamed only to logged-in users with jurisdiction over the member. */
    public function photo(Request $request, Member $member)
    {
        abort_unless($request->user()->canView('members') || $request->user()->canView('directory'), 403);
        $this->svc($request)->authorize($member);
        abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

        return Storage::disk('local')->response($member->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function removePhoto(Request $request, Member $member)
    {
        abort_unless($request->user()->canManage('members'), 403);
        $this->svc($request)->authorize($member);
        PhotoService::delete($member->photo_path);
        $member->update(['photo_path' => null]);

        return back()->with('ok', 'Photo removed.');
    }

    /** Printable photo directory, grouped by area and ordered by designation rank. */
    public function directory(Request $request)
    {
        $members = null;
        if ($request->hasAny(['state_id', 'level', 'district_id'])) {
            $request->query->set('status', 'active');
            $members = $this->svc($request)->query($request)
                ->orderByRaw('COALESCE((SELECT seniority FROM designations WHERE designations.id = members.designation_id), 999)')
                ->orderBy('name')
                ->limit(1000)->get();
            AuditLog::record('directory', null, http_build_query($request->only(['state_id', 'district_id', 'level', 'type'])));
        }

        return view('members.directory', $this->formData($request) + ['members' => $members]);
    }

    /** District dropdown data (AJAX). */
    public function districts(State $state)
    {
        return $state->districts()->get(['id', 'name']);
    }

    private function formData(Request $request): array
    {
        $u = $request->user();
        $states = State::orderBy('name')->when($u->state_id && ! $u->isNational(), fn ($q) => $q->where('id', $u->state_id))->get();

        return [
            'states' => $states,
            'districtsByState' => District::when(! $u->isNational(), fn ($q) => $q->where('state_id', $u->state_id))
                ->when($u->role === 'district_admin', fn ($q) => $q->where('id', $u->district_id))
                ->orderBy('name')->get(['id', 'state_id', 'name'])->groupBy('state_id'),
            'designations' => Designation::orderBy('level')->orderBy('seniority')->get(),
            'levels' => Lookup::options('level'),
            'types' => Lookup::options('member_type'),
            'statuses' => Lookup::options('member_status'),
            'genders' => Lookup::options('gender'),
            'professions' => Lookup::options('profession'),
            'educations' => Lookup::options('education'),
            'customFields' => CustomField::for('member'),
            'requiredFields' => (array) setting('member_required_fields', []),
        ];
    }
}

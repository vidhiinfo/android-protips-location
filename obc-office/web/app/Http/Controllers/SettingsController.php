<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CustomField;
use App\Models\Lookup;
use App\Models\Setting;
use App\Models\User;
use App\Services\BiodataService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Everything an administrator can configure without a developer:
 *  - General: branding, logo, colours, message templates, formats, member-form rules
 *  - Lists: every dropdown in the app
 *  - Fields: extra custom fields on member records
 *  - Access: module on/off switches and the role → module permission matrix
 */
class SettingsController extends Controller implements HasMiddleware
{
    /** Member fields an admin may mark as mandatory. */
    public const MEMBER_FIELDS = [
        'photo' => 'Photo', 'mobile' => 'Mobile', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'father_name' => "Father's / Husband's name",
        'gender' => 'Gender', 'dob' => 'Date of birth', 'address' => 'Address', 'profession' => 'Profession',
        'education' => 'Education', 'designation_id' => 'Designation', 'appointed_on' => 'Appointment date',
    ];

    public static function middleware(): array
    {
        return [new Middleware('module:settings,manage')];
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'general');

        return view('settings.index', [
            'tab' => $tab,
            'memberFields' => self::MEMBER_FIELDS,
            'groups' => Lookup::GROUPS,
            'group' => $request->query('group', 'petition_category'),
            'lookups' => Lookup::where('group', $request->query('group', 'petition_category'))->orderBy('sort')->orderBy('label')->get(),
            'entity' => $entity = $request->query('entity') === 'biodata' ? 'biodata' : 'member',
            'customFields' => CustomField::where('entity', $entity)->orderBy('sort')->get(),
            'biodataFields' => BiodataService::OPTIONAL_FIELDS,
            'modules' => config('office.modules'),
            'roles' => collect(User::ROLES)->except('super_admin'),
        ]);
    }

    public function saveGeneral(Request $request)
    {
        $data = $request->validate([
            'org_name' => 'required|string|max:150',
            'org_short_name' => 'nullable|string|max:60',
            'president_name' => 'required|string|max:150',
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer_text' => 'nullable|string|max:255',
            'greeting_signature' => 'nullable|string|max:255',
            'birthday_template' => 'nullable|string|max:2000',
            'anniversary_template' => 'nullable|string|max:2000',
            'greeting_days_ahead' => 'required|integer|min:0|max:31',
            'petition_prefix' => 'required|alpha_dash|max:10',
            'petition_default_due_days' => 'required|integer|min:1|max:365',
            'petition_closed_status' => ['required', Rule::in(array_keys(Lookup::options('petition_status', true)))],
            'member_required_fields' => 'nullable|array',
            'member_required_fields.*' => Rule::in(array_keys(self::MEMBER_FIELDS)),
            'photo_max_px' => 'required|integer|min:200|max:2000',
            'date_format' => ['required', Rule::in(['d M Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'])],
            'datetime_format' => ['required', Rule::in(['d M Y, h:i A', 'd/m/Y H:i', 'd-m-Y h:i A', 'Y-m-d H:i'])],
            'per_page' => 'required|integer|min:10|max:200',
            'logo' => 'nullable|image|max:2048',
            'biodata_prefix' => 'required|alpha_dash|max:10',
            'biodata_max_file_mb' => 'required|integer|min:1|max:50',
            'biodata_required_fields' => 'nullable|array',
            'biodata_required_fields.*' => Rule::in(array_keys(BiodataService::OPTIONAL_FIELDS)),
        ]);
        $data['biodata_required_fields'] = array_values($data['biodata_required_fields'] ?? []);

        foreach (['require_consent', 'state_entries_need_approval', 'duplicate_check_mobile'] as $flag) {
            Setting::put($flag, $request->boolean($flag));
        }
        // name, level and type are always required by the system.
        Setting::put('member_required_fields', array_values(array_unique(array_merge(['name', 'level'], $data['member_required_fields'] ?? []))));
        unset($data['member_required_fields']);

        if ($request->hasFile('logo')) {
            $old = setting('logo_path');
            Setting::put('logo_path', $request->file('logo')->store('branding', 'public'));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($request->boolean('remove_logo')) {
            Storage::disk('public')->delete((string) setting('logo_path'));
            Setting::put('logo_path', null);
        }
        unset($data['logo']);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }
        AuditLog::record('settings', null, 'general');

        return redirect()->route('settings.index', ['tab' => 'general'])->with('ok', 'Settings saved.');
    }

    public function saveAccess(Request $request)
    {
        $modules = config('office.modules');
        $enabled = collect($modules)->keys()->mapWithKeys(fn ($m) => [$m => $request->boolean("enabled.$m")])->all();
        $enabled['settings'] = true; // never lock everyone out of Settings
        $enabled['dashboard'] = true;

        $perms = [];
        foreach (array_keys(User::ROLES) as $role) {
            if ($role === 'super_admin') {
                continue;
            }
            foreach ($modules as $key => $m) {
                $val = $request->input("perm.$role.$key", 'none');
                $perms[$role][$key] = in_array($val, $m['levels'], true) ? $val : 'none';
            }
        }

        Setting::put('modules_enabled', $enabled);
        Setting::put('permissions', $perms);
        AuditLog::record('settings', null, 'access');

        return redirect()->route('settings.index', ['tab' => 'access'])->with('ok', 'Access settings saved.');
    }

    public function storeLookup(Request $request)
    {
        $data = $request->validate([
            'group' => ['required', Rule::in(array_keys(Lookup::GROUPS))],
            'label' => 'required|string|max:100',
            'value' => 'nullable|alpha_dash|max:60',
            'color' => 'nullable|string|max:20',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);
        $data['value'] = ($data['value'] ?? null) ?: Str::slug($data['label'], '_');
        validator($data, ['value' => [Rule::unique('lookups')->where('group', $data['group'])]])->validate();
        Lookup::create($data + ['sort' => $data['sort'] ?? 100, 'active' => true]);

        return redirect()->route('settings.index', ['tab' => 'lists', 'group' => $data['group']])->with('ok', 'Option added.');
    }

    public function updateLookup(Request $request, Lookup $lookup)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);
        $data['active'] = $lookup->locked ? true : $request->boolean('active');
        $lookup->update($data);

        return redirect()->route('settings.index', ['tab' => 'lists', 'group' => $lookup->group])->with('ok', 'Option updated.');
    }

    public function destroyLookup(Lookup $lookup)
    {
        abort_if($lookup->locked, 422, 'This option is used by the system and cannot be deleted. You can rename it.');
        $group = $lookup->group;
        $lookup->delete();

        return redirect()->route('settings.index', ['tab' => 'lists', 'group' => $group])->with('ok', 'Option deleted.');
    }

    public function storeField(Request $request)
    {
        $data = $this->validateField($request);
        $entity = $request->input('entity') === 'biodata' ? 'biodata' : 'member';
        $data['key'] = Str::slug($request->input('key') ?: $data['label'], '_');
        validator($data, ['key' => ['required', 'max:40', Rule::unique('custom_fields')->where('entity', $entity)]])->validate();
        CustomField::create($data + ['entity' => $entity]);
        AuditLog::record('settings', null, "custom field {$entity}.{$data['key']} added");

        return redirect()->route('settings.index', ['tab' => 'fields', 'entity' => $entity])->with('ok', 'Field added.');
    }

    public function updateField(Request $request, CustomField $field)
    {
        $field->update($this->validateField($request));

        return redirect()->route('settings.index', ['tab' => 'fields', 'entity' => $field->entity])->with('ok', 'Field updated.');
    }

    public function destroyField(CustomField $field)
    {
        $field->delete(); // existing values stay in the record's "extra" data and reappear if the key is re-added

        return redirect()->route('settings.index', ['tab' => 'fields', 'entity' => $field->entity])->with('ok', 'Field removed.');
    }

    private function validateField(Request $request): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'type' => ['required', Rule::in(array_keys(CustomField::TYPES))],
            'options' => 'nullable|required_if:type,select|string|max:5000',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);
        foreach (['required', 'show_in_list', 'active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['sort'] ??= 100;

        return $data;
    }
}

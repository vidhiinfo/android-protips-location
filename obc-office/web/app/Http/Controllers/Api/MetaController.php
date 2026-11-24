<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Designation;
use App\Models\District;
use App\Models\Lookup;
use App\Models\State;
use Illuminate\Http\Request;

/** Master data for app dropdowns and offline caching. */
class MetaController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = $request->user();

        return [
            'states' => State::orderBy('name')->get(['id', 'name', 'code', 'is_ut']),
            'districts' => District::orderBy('name')
                ->when(! $u->isNational(), fn ($q) => $q->where('state_id', $u->state_id))
                ->get(['id', 'state_id', 'name']),
            'designations' => Designation::orderBy('level')->orderBy('seniority')->get(['id', 'level', 'title', 'seniority', 'max_count']),
            'lookups' => collect(array_keys(Lookup::GROUPS))->mapWithKeys(fn ($g) => [$g => collect(Lookup::options($g))
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()]),
            'member_custom_fields' => CustomField::for('member')->map(fn ($f) => [
                'key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'options' => $f->optionList(),
                'required' => $f->required,
            ]),
            'member_required_fields' => setting('member_required_fields'),
            'biodata_custom_fields' => CustomField::for('biodata')->map(fn ($f) => [
                'key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'options' => $f->optionList(), 'required' => $f->required,
            ]),
            'biodata_required_fields' => setting('biodata_required_fields'),
            'require_consent' => (bool) setting('require_consent'),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'gender' => $this->gender,
            'dob' => $this->dob?->toDateString(),
            'mobile' => $this->mobile,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->address,
            'profession' => $this->profession,
            'education' => $this->education,
            'skills' => $this->skills,
            'level' => $this->level,
            'level_label' => lookup_label('level', $this->level),
            'state' => $this->state ? ['id' => $this->state->id, 'name' => $this->state->name] : null,
            'district' => $this->district ? ['id' => $this->district->id, 'name' => $this->district->name] : null,
            'mandal' => $this->mandal,
            'designation' => $this->designation ? ['id' => $this->designation->id, 'title' => $this->designation->title] : null,
            'appointed_on' => $this->appointed_on?->toDateString(),
            'tenure_end' => $this->tenure_end?->toDateString(),
            'status' => $this->status,
            'consent' => (bool) $this->consent,
            'extra' => $this->extra ?? (object) [],
            'photo_url' => $this->photo_path ? route('api.members.photo', $this->resource).'?v='.substr(md5($this->photo_path), 0, 8) : null,
            'area' => $this->area(),
            'completeness' => $this->completeness(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

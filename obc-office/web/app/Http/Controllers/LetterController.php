<?php

namespace App\Http\Controllers;

use App\Models\Letter;

class LetterController extends SimpleCrudController
{
    protected string $model = Letter::class;

    protected string $module = 'letters';

    protected string $routeName = 'letters';

    protected string $title = 'Letters Register (Inward / Outward)';

    protected string $singular = 'Letter';

    protected array $search = ['ref_no', 'party', 'subject', 'summary'];

    protected array $filters = ['direction', 'status'];

    protected ?string $dateColumn = 'letter_date';

    protected function fields(): array
    {
        return [
            'direction' => ['label' => 'Inward / Outward', 'type' => 'select', 'options' => ['inward' => 'Inward', 'outward' => 'Outward'], 'rules' => 'required|in:inward,outward', 'list' => true],
            'ref_no' => ['label' => 'Reference no.', 'rules' => 'nullable|string|max:60', 'list' => true],
            'letter_date' => ['label' => 'Letter date', 'type' => 'date', 'rules' => 'nullable|date', 'list' => true],
            'party' => ['label' => 'From / To', 'rules' => 'nullable|string|max:200', 'list' => true],
            'subject' => ['label' => 'Subject', 'rules' => 'required|string|max:255', 'list' => true],
            'summary' => ['label' => 'Summary', 'type' => 'textarea', 'rules' => 'nullable|string|max:5000'],
            'action_taken' => ['label' => 'Action taken', 'rules' => 'nullable|string|max:255'],
            'status' => ['label' => 'Status', 'type' => 'lookup:letter_status', 'rules' => 'required', 'list' => true],
        ];
    }

    protected function defaults(): array
    {
        return ['direction' => 'inward', 'letter_date' => now()->toDateString(), 'status' => 'pending'];
    }
}

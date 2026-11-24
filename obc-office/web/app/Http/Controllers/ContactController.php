<?php

namespace App\Http\Controllers;

use App\Models\Contact;

class ContactController extends SimpleCrudController
{
    protected string $model = Contact::class;

    protected string $module = 'contacts';

    protected string $routeName = 'contacts';

    protected string $title = 'VIP Contacts';

    protected string $singular = 'Contact';

    protected array $search = ['name', 'organisation', 'designation', 'mobile', 'email'];

    protected array $filters = ['category', 'state_id'];

    protected string $orderBy = 'name';

    protected string $orderDir = 'asc';

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Name', 'rules' => 'required|string|max:150', 'list' => true],
            'category' => ['label' => 'Category', 'type' => 'lookup:contact_category', 'rules' => 'nullable', 'list' => true],
            'designation' => ['label' => 'Designation', 'rules' => 'nullable|string|max:150', 'list' => true],
            'organisation' => ['label' => 'Organisation / Constituency', 'rules' => 'nullable|string|max:150'],
            'mobile' => ['label' => 'Mobile', 'type' => 'tel', 'rules' => 'nullable|string|max:15', 'list' => true],
            'email' => ['label' => 'Email', 'type' => 'email', 'rules' => 'nullable|email|max:150'],
            'state_id' => ['label' => 'State', 'type' => 'state', 'rules' => 'nullable', 'list' => true],
            'dob' => ['label' => 'Date of birth', 'type' => 'date', 'rules' => 'nullable|date'],
            'anniversary' => ['label' => 'Anniversary', 'type' => 'date', 'rules' => 'nullable|date'],
            'last_met' => ['label' => 'Last met', 'type' => 'date', 'rules' => 'nullable|date', 'list' => true],
            'notes' => ['label' => 'Notes', 'type' => 'textarea', 'rules' => 'nullable|string|max:5000'],
        ];
    }
}

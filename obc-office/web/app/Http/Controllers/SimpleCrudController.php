<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lookup;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Field-driven CRUD for the simpler office registers (schedule, visitors, contacts, letters...).
 *
 * A field definition looks like:
 *   'start_at' => ['label' => 'Starts', 'type' => 'datetime', 'rules' => 'required|date', 'list' => true]
 * Types: text, textarea, date, datetime, number, tel, email, lookup:<group>, state, user, select (with 'options').
 */
abstract class SimpleCrudController extends Controller implements HasMiddleware
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $module;     // permission module key, e.g. "visitors"

    protected string $routeName;  // resource route prefix, e.g. "visitors"

    protected string $title;

    protected string $singular;

    protected array $search = ['name'];

    protected array $filters = []; // field names offered as dropdown filters

    protected ?string $dateColumn = null; // enables from/to date filter

    protected string $orderBy = 'id';

    protected string $orderDir = 'desc';

    abstract protected function fields(): array;

    public static function middleware(): array
    {
        $module = (new static)->module;

        return [
            new Middleware("module:$module"),
            new Middleware("module:$module,manage", except: ['index', 'show']),
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        return $this->model::query();
    }

    /** Defaults for the "new" form. */
    protected function defaults(): array
    {
        return [];
    }

    public function index(Request $request)
    {
        $q = $this->baseQuery($request);

        if ($term = trim((string) $request->query('q'))) {
            $q->where(function ($w) use ($term) {
                foreach ($this->search as $col) {
                    $w->orWhere($col, 'like', "%{$term}%");
                }
            });
        }
        foreach ($this->filters as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($this->dateColumn) {
            if ($request->filled('from')) {
                $q->whereDate($this->dateColumn, '>=', $request->query('from'));
            }
            if ($request->filled('to')) {
                $q->whereDate($this->dateColumn, '<=', $request->query('to'));
            }
        }

        $rows = $q->orderBy($this->orderBy, $this->orderDir)->paginate((int) setting('per_page', 25))->withQueryString();

        return view('crud.index', $this->viewData() + ['rows' => $rows]);
    }

    public function create()
    {
        $record = new $this->model($this->defaults());

        return view('crud.form', $this->viewData() + ['record' => $record]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $record = new $this->model;
        $record->forceFill($data);
        if (in_array('created_by', $record->getConnection()->getSchemaBuilder()->getColumnListing($record->getTable()), true)) {
            $record->created_by = $request->user()->id;
        }
        $record->save();
        AuditLog::record('create', $record, $this->describe($record));

        return redirect()->route("{$this->routeName}.index")->with('ok', "{$this->singular} added.");
    }

    public function show(string $id)
    {
        return redirect()->route("{$this->routeName}.edit", $id);
    }

    public function edit(string $id)
    {
        $record = $this->model::findOrFail($id);

        return view('crud.form', $this->viewData() + ['record' => $record]);
    }

    public function update(Request $request, string $id)
    {
        $record = $this->model::findOrFail($id);
        $record->forceFill($this->validated($request))->save();
        AuditLog::record('update', $record, $this->describe($record));

        return redirect()->route("{$this->routeName}.index")->with('ok', "{$this->singular} updated.");
    }

    public function destroy(string $id)
    {
        $record = $this->model::findOrFail($id);
        AuditLog::record('delete', $record, $this->describe($record));
        $record->delete();

        return redirect()->route("{$this->routeName}.index")->with('ok', "{$this->singular} deleted.");
    }

    protected function validated(Request $request): array
    {
        $rules = collect($this->fields())->mapWithKeys(function ($f, $name) {
            $rules = $f['rules'] ?? 'nullable';
            $type = $f['type'] ?? 'text';
            if (str_starts_with($type, 'lookup:')) {
                $rules .= '|in:'.implode(',', array_keys(Lookup::options(substr($type, 7), true)));
            } elseif ($type === 'state') {
                $rules .= '|exists:states,id';
            } elseif ($type === 'user') {
                $rules .= '|exists:users,id';
            }

            return [$name => $rules];
        })->all();

        return $request->validate($rules);
    }

    protected function describe(Model $record): string
    {
        return (string) ($record->name ?? $record->title ?? $record->subject ?? '#'.$record->getKey());
    }

    /** Options for every select-like field, resolved once per request. */
    protected function options(): array
    {
        $opts = [];
        foreach ($this->fields() as $name => $f) {
            $type = $f['type'] ?? 'text';
            $opts[$name] = match (true) {
                str_starts_with($type, 'lookup:') => Lookup::options(substr($type, 7), true),
                $type === 'state' => State::orderBy('name')->pluck('name', 'id')->all(),
                $type === 'user' => User::where('active', true)->orderBy('name')->get()
                    ->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->jurisdictionLabel().')'])->all(),
                $type === 'select' => $f['options'] ?? [],
                default => null,
            };
        }

        return array_filter($opts, fn ($o) => $o !== null);
    }

    protected function viewData(): array
    {
        return [
            'title' => $this->title,
            'singular' => $this->singular,
            'routeName' => $this->routeName,
            'module' => $this->module,
            'fields' => $this->fields(),
            'options' => $this->options(),
            'filters' => $this->filters,
            'dateFilter' => (bool) $this->dateColumn,
            'canManage' => auth()->user()->canManage($this->module),
        ];
    }
}

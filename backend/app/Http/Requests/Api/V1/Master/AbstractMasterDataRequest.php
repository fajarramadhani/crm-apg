<?php

namespace App\Http\Requests\Api\V1\Master;

use App\Models\ApplicationModule;
use App\Models\Holiday;
use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class AbstractMasterDataRequest extends FormRequest
{
    protected const ENTITY = '';

    protected const UPDATE = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if ($this->has('code')) {
            $values['code'] = strtoupper(trim((string) $this->input('code')));
        }
        if ($this->has('key')) {
            $values['key'] = strtolower(trim((string) $this->input('key')));
        }
        if ($this->has('working_days') && is_array($this->input('working_days'))) {
            $values['working_days'] = array_values(array_map('intval', $this->input('working_days')));
        }
        if (static::ENTITY === 'holiday') {
            $values['working_calendar_id'] = $this->calendarId();
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $id = $this->modelId();
        $code = ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'];
        $name = ['required', 'string', 'max:255'];
        $active = ['sometimes', 'boolean'];

        return match (static::ENTITY) {
            'division' => ['code' => [...$code, Rule::unique('divisions', 'code')->ignore($id)], 'name' => $name, 'description' => ['nullable', 'string'], 'parent_id' => ['nullable', 'integer', 'exists:divisions,id', Rule::notIn(array_filter([$id]))], 'is_active' => $active],
            'branch' => ['code' => [...$code, Rule::unique('branches', 'code')->ignore($id)], 'name' => $name, 'address' => ['nullable', 'string'], 'city' => ['nullable', 'string', 'max:255'], 'is_active' => $active],
            'application' => ['code' => [...$code, Rule::unique('applications', 'code')->ignore($id)], 'name' => $name, 'description' => ['nullable', 'string'], 'owner_division_id' => ['nullable', 'integer', 'exists:divisions,id'], 'is_active' => $active],
            'module' => ['code' => [...$code, Rule::unique('application_modules', 'code')->where(fn ($q) => $q->where('application_id', $this->applicationId()))->ignore($id)], 'name' => $name, 'description' => ['nullable', 'string'], 'is_active' => $active],
            'category' => ['code' => [...$code, Rule::unique('ticket_categories', 'code')->ignore($id)], 'name' => $name, 'type' => ['required', Rule::in(TicketCategory::TYPES)], 'description' => ['nullable', 'string'], 'is_active' => $active],
            'priority' => ['key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('ticket_priorities', 'key')->ignore($id)], 'name' => $name, 'level' => ['required', 'integer', 'min:1', 'max:100', Rule::unique('ticket_priorities', 'level')->ignore($id)], 'description' => ['nullable', 'string'], 'is_active' => $active],
            'sla' => ['priority_id' => ['required', 'integer', 'exists:ticket_priorities,id'], 'response_minutes' => ['nullable', 'integer', 'min:1'], 'resolution_minutes' => ['required', 'integer', 'min:1'], 'working_calendar_id' => ['required', 'integer', 'exists:working_calendars,id'], 'is_active' => $active],
            'calendar' => ['code' => [...$code, Rule::unique('working_calendars', 'code')->ignore($id)], 'name' => $name, 'timezone' => ['required', 'timezone:all'], 'workday_start' => ['required', 'date_format:H:i'], 'workday_end' => ['required', 'date_format:H:i', 'after:workday_start'], 'working_days' => ['required', 'array', 'min:1', 'max:7'], 'working_days.*' => ['required', 'integer', 'distinct', 'between:1,7'], 'is_active' => $active],
            'holiday' => ['working_calendar_id' => ['required', 'integer', 'exists:working_calendars,id'], 'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')->where(fn ($q) => $q->where('working_calendar_id', $this->input('working_calendar_id')))->ignore($id)], 'name' => $name, 'is_recurring' => ['sometimes', 'boolean']],
            default => [],
        };
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->boolean('is_active', true) === false) {
                return;
            }
            $relations = ['parent_id' => 'divisions', 'owner_division_id' => 'divisions', 'priority_id' => 'ticket_priorities', 'working_calendar_id' => 'working_calendars'];
            foreach ($relations as $field => $table) {
                $value = $this->input($field);
                if ($value && ! DB::table($table)->where('id', $value)->where('is_active', true)->exists()) {
                    $validator->errors()->add($field, 'The selected active relation is inactive.');
                }
            }
            if (static::ENTITY === 'sla' && $this->boolean('is_active', true)) {
                $query = SlaPolicy::query()->where('priority_id', $this->input('priority_id'))->where('working_calendar_id', $this->input('working_calendar_id'))->where('is_active', true);
                if ($this->modelId()) {
                    $query->whereKeyNot($this->modelId());
                }
                if ($query->exists()) {
                    $validator->errors()->add('priority_id', 'An active SLA policy already exists for this priority and calendar.');
                }
            }
            if (static::ENTITY === 'holiday') {
                $query = Holiday::query()->where('working_calendar_id', $this->calendarId())->whereDate('date', (string) $this->input('date'));
                if ($this->modelId()) {
                    $query->whereKeyNot($this->modelId());
                }
                if ($query->exists()) {
                    $validator->errors()->add('date', 'A holiday already exists on this date for the selected calendar.');
                }
            }
        }];
    }

    private function modelId(): ?int
    {
        foreach (['division', 'branch', 'application', 'module', 'category', 'priority', 'policy', 'calendar', 'holiday'] as $key) {
            $value = $this->route($key);
            if (is_object($value) && isset($value->id)) {
                return (int) $value->id;
            }
        }

        return null;
    }

    public function applicationId(): ?int
    {
        $value = $this->route('application');
        if ($value !== null) {
            return is_object($value) ? (int) $value->id : (int) $value;
        }

        $module = $this->route('module');
        if (! $module) {
            return null;
        }

        return is_object($module)
            ? (int) $module->application_id
            : (int) ApplicationModule::query()->whereKey($module)->value('application_id');
    }

    public function calendarId(): ?int
    {
        $value = $this->route('calendar');
        if ($value !== null) {
            return is_object($value) ? (int) $value->id : (int) $value;
        }

        $holiday = $this->route('holiday');
        if (! $holiday) {
            return null;
        }

        return is_object($holiday)
            ? (int) $holiday->working_calendar_id
            : (int) Holiday::query()->whereKey($holiday)->value('working_calendar_id');
    }
}

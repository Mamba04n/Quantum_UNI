<?php

namespace App\Application\Http\Requests;

use App\Models\ClientMachine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ClientMachine::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('entities', 'id')->where('type', 'cliente'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:machines,serial_number'],
            'purchase_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['activo', 'mantenimiento', 'fuera_de_servicio'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags((string) $this->input('name')),
            'model' => strip_tags((string) $this->input('model')),
            'serial_number' => strip_tags((string) $this->input('serial_number')),
            'status' => strip_tags((string) $this->input('status')),
        ]);
    }
}

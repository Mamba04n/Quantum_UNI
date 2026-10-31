<?php

namespace App\Http\Requests\Api\V1\Finance;

use App\Domain\Finanzas\Api\Enums\FinanceTokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueFinanceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'enrollment_code' => ['required', 'string', 'size:48'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in(FinanceTokenAbility::requestable())],
        ];
    }
}

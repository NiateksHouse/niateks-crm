<?php
namespace App\Http\Requests;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        return $company ? $this->user()->can('update', $company) : $this->user()->can('create', Company::class);
    }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'roles' => ['required', 'array', 'min:1', 'max:2'],
            'roles.*' => ['required', 'in:customer,supplier', 'distinct'],
            'supply_category_ids' => ['nullable', 'array', 'max:30'],
            'supply_category_ids.*' => ['required', 'integer', 'distinct', 'exists:supply_categories,id'],
            'version' => [$this->isMethod('POST') ? 'nullable' : 'required', 'integer', 'min:1'],
        ];
    }
    public function after(): array
    {
        return [function ($validator) {
            if ($this->input('supply_category_ids') && ! in_array('supplier', (array) $this->input('roles', []), true)) {
                $validator->errors()->add('supply_category_ids', 'Tedarik alanı seçmek için Tedarikçi rolünü işaretleyin.');
            }
        }];
    }
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['name', 'country_code', 'email', 'phone'] as $key) {
            $value = $this->input($key);
            if (! is_string($value)) continue;
            $value = trim($value);
            $normalized[$key] = match ($key) {
                'country_code' => strtoupper($value),
                'email' => $value === '' ? null : mb_strtolower($value),
                'phone' => $value === '' ? null : preg_replace('/[^+0-9]/', '', $value),
                default => $value,
            };
        }
        $this->merge($normalized);
    }
}

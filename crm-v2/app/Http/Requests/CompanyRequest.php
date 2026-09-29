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
            'version' => [$this->isMethod('POST') ? 'nullable' : 'required', 'integer', 'min:1'],
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->name), 'country_code' => strtoupper(trim((string) $this->country_code)),
            'email' => $this->email ? mb_strtolower(trim($this->email)) : null,
            'phone' => $this->phone ? preg_replace('/[^+0-9]/', '', $this->phone) : null]);
    }
}

<?php

namespace App\Http\Requests\Notice;

use App\Enums\InstrumentType;
use App\Enums\MonitoringReportRequestDeadline;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class NoticeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(Role::fomentoRoles());
    }

    public function messages(): array
    {
        return [
            'nup.unique' => 'Este número de processo mãe já está em uso.',
            'budget_allocation_nup.unique' => 'Este número de processo de dotação orçamentária já está em uso.',

            'total_notice_amount.numeric' => 'O valor total do edital deve ser um número válido.',
            'total_notice_amount.min' => 'O valor total do edital deve ser maior que zero.',
            'total_commitment_amount.min' => 'O valor do compromisso deve ser maior ou igual a zero.',
        ];
    }

    public function rules(): array
    {
        $notice = $this->route('notice');

        return [
            'nup' => [
                'sometimes',
                'string',
                Rule::unique('notices', 'nup')->ignore($notice->id),
            ],

            'instrument_type' => [
                'nullable',
                new Enum(InstrumentType::class),
                function (string $attribute, mixed $value, \Closure $fail) use ($notice) {
                    // Verifica se houve tentativa de alteração do valor existente
                    if ($notice && $notice->instrument_type !== $value) {
                        if (! $this->user()->hasRole(Role::SUPER_ADMIN)) {
                            $fail('Você não tem permissão para alterar o tipo de instrumento do edital.');
                        }
                    }
                },
            ],

            'name' => ['sometimes', 'string'],
            'notice_url' => ['nullable', 'string'],
            'external_id' => ['nullable', 'string'],

            'total_notice_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],

            'total_commitment_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'installments' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'process_manager' => ['nullable', 'string'],
            'process_manager_email' => ['nullable', 'email'],

            'budget_allocation_nup' => [
                'nullable',
                'string',
                Rule::unique('notices', 'budget_allocation_nup')
                    ->ignore($notice->id),
            ],

            'budget_allocation_request_date' => ['nullable', 'date'],
            'creditor_registration_nup' => ['nullable', 'string'],
            'creditor_registration_request_date' => ['nullable', 'date'],
            'monitoring_report_request_deadline' => [
                'sometimes',
                'required',
                new Enum(MonitoringReportRequestDeadline::class),
            ],
        ];
    }
}

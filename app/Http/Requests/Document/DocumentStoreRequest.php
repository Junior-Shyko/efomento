<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentImagePosition;
use App\Enums\DocumentImageSection;
use App\Enums\DocumentPhase;
use App\Enums\DocumentType;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = DocumentType::tryFrom($this->string('type')->toString());

        if ($type?->isBudgetOpinion()) {
            return $this->user()?->hasAnyRole(Role::budgetRoles()) ?? false;
        }

        if ($type?->isJuridicalReference()) {
            return $this->user()?->hasAnyRole(Role::legalAnalysisRoles()) ?? false;
        }

        return true;
    }

    public function rules(): array
    {
        $noticeRules = ['required', 'exists:notices,id'];
        $type = DocumentType::tryFrom($this->input('type'));

        if ($type?->isNoticeLevel() && blank($this->input('project_id'))) {
            $noticeRules[] = Rule::unique('documents', 'notice_id')
                ->where(fn ($query) => $query
                    ->where('type', $type->value)
                    ->where('phase', $this->input('phase'))
                    ->whereNull('project_id')
                    ->whereNull('deleted_at'));
        }

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'phase' => ['required', Rule::enum(DocumentPhase::class)],
            'body' => ['required', 'string'],
            'notice_id' => $noticeRules,
            'project_id' => [
                Rule::requiredIf(fn () => ! (DocumentType::tryFrom($this->input('type'))?->isNoticeLevel() ?? false)),
                'nullable',
                'exists:projects,id',
            ],
            'images' => ['nullable', 'array'],
            'images.*.section' => ['required_with:images', Rule::enum(DocumentImageSection::class)],
            'images.*.position' => ['required_with:images', Rule::enum(DocumentImagePosition::class)],
            'images.*.path' => [
                'required_with:images',
                'string',
                'regex:/\Adocuments\/[A-Za-z0-9_-]+\.(?:gif|jpe?g|png)\z/i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'notice_id.required' => 'O edital é obrigatório.',
            'notice_id.exists' => 'O edital informado não existe.',
            'notice_id.unique' => 'Já existe um documento deste tipo para este edital.',
            'project_id.required' => 'O projeto é obrigatório.',
            'project_id.exists' => 'O projeto informado não existe.',
            'type.required' => 'O tipo do documento é obrigatório.',
            'phase.required' => 'A fase do documento é obrigatória.',
            'body.required' => 'O conteúdo do documento é obrigatório.',
        ];
    }
}

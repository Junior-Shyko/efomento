<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentImagePosition;
use App\Enums\DocumentImageSection;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        if (! $document instanceof Document) {
            return false;
        }

        if ($document->type->isBudgetOpinion()) {
            return $this->user()?->hasAnyRole(Role::budgetRoles()) ?? false;
        }

        if ($document->type->isJuridicalReference()) {
            return $this->user()?->hasAnyRole(Role::legalAnalysisRoles()) ?? false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'images' => ['nullable', 'array'],
            'images.*.section' => ['required_with:images', Rule::enum(DocumentImageSection::class)],
            'images.*.position' => ['required_with:images', Rule::enum(DocumentImagePosition::class)],
            'images.*.path' => [
                'required_with:images',
                'string',
                'regex:/\Adocuments\/[A-Za-z0-9_-]+\.(?:gif|jpe?g|png|webp)\z/i',
            ],
        ];
    }
}

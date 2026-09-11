<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class FileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'grp' => ['required', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'private' => ['nullable', 'boolean'],
        ];
    }
}

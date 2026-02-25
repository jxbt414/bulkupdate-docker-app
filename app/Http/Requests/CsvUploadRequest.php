<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CsvUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'] // Max 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'csv.required' => 'Please select a CSV file to upload.',
            'csv.file' => 'The uploaded file must be a valid file.',
            'csv.mimes' => 'The file must be a CSV file.',
            'csv.max' => 'The file size must not exceed 10MB.'
        ];
    }
} 
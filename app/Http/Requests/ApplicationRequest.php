<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('seeker');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:150', 'phone' => 'required|string|max:30', 'portfolio' => 'nullable|url:https|max:255', 'cv' => 'required|file|mimes:pdf|extensions:pdf|max:'.config('sharekerja.cv_max_kb')];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruiter');
    }

    public function rules(): array
    {
        return ['logo' => 'nullable|image|mimes:jpeg,png|extensions:jpg,jpeg,png|max:2048|dimensions:max_width=2000,max_height=2000', 'name' => 'required|string|max:150', 'industry' => 'required|string|max:100', 'email' => 'required|email|max:255', 'phone' => 'required|string|max:30', 'position' => 'required|string|max:100', 'website' => 'nullable|url:https|max:255', 'location' => 'required|string|max:150', 'description' => 'required|string|min:20|max:5000'];
    }
}

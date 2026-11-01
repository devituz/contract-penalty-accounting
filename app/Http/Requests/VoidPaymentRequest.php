<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('payment'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['reason' => 'bekor qilish sababi'];
    }
}

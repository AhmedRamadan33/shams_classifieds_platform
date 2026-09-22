<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportListingRequest extends FormRequest
{
    /** Errors are shown inside the report modal, so they live in their own bag. */
    protected $errorBag = 'report';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'note' => [
                Rule::requiredIf(fn () => $this->input('reason') === ReportReason::Other->value),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('app.report.reason'), 'note' => __('app.report.note')];
    }
}

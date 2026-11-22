<?php

namespace Modules\Membership\Http\Requests;

use App\Models\School;
use App\Rules\NigerianPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Membership\Models\MembershipApplication;

/**
 * MEM-1: "Application holds all required fields and documents."
 * Fields follow the PRD registry: name, CAC/registration number, ministry
 * approval, address, GPS, proprietor, chapter, pupils, levels.
 */
class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $documentRule = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.config('napps.membership.document_max_kb')];

        return [
            'name' => ['required', 'string', 'max:160'],
            'registration_number' => [
                'required', 'string', 'max:64',
                Rule::unique('schools', 'registration_number')->whereNull('deleted_at'),
            ],
            'ministry_approval_number' => ['nullable', 'string', 'max:64'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => [Rule::in(School::LEVELS)],
            'pupil_count' => ['required', 'integer', 'min:1', 'max:20000'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'chapter_id' => ['required', 'integer', Rule::exists('chapters', 'id')->where('state_id', $this->integer('state_id'))],
            'lga' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            // Nigeria's bounding box: catches swapped or mistyped coordinates.
            'latitude' => ['nullable', 'numeric', 'between:4,14', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:2.5,15', 'required_with:latitude'],
            'phone' => ['required', 'string', new NigerianPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'cac_certificate' => ['required', ...$documentRule],
            'ministry_approval' => ['nullable', ...$documentRule],
            'premises_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('napps.membership.document_max_kb')],
            'declaration' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_number.unique' => __('A school with this registration number is already registered or applying. Contact your chapter if this is your school.'),
            'chapter_id.exists' => __('Choose a chapter in the state you selected.'),
            'latitude.between' => __('That location is outside Nigeria. Use the button to fill it in, or leave it empty.'),
            'longitude.between' => __('That location is outside Nigeria. Use the button to fill it in, or leave it empty.'),
            'cac_certificate.required' => __('Upload the CAC registration certificate (photo or PDF).'),
            'levels.required' => __('Choose at least one level.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'registration_number' => __('CAC / registration number'),
            'ministry_approval_number' => __('ministry approval number'),
            'pupil_count' => __('number of pupils'),
            'state_id' => __('state'),
            'chapter_id' => __('chapter'),
            'lga' => __('LGA'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $open = MembershipApplication::withoutGlobalScopes()
                    ->where('applicant_id', $this->user()?->getKey())
                    ->whereIn('status', ['submitted', 'verified'])
                    ->count();

                if ($open >= config('napps.membership.max_open_applications')) {
                    $validator->errors()->add('name', __('You already have :count applications waiting for a decision. Wait for those first.', ['count' => $open]));
                }
            },
        ];
    }

    /**
     * Validated fields for the schools table (state follows the chapter).
     *
     * @return array<string, mixed>
     */
    public function schoolData(): array
    {
        $data = $this->safe()->only([
            'name', 'ministry_approval_number', 'levels', 'pupil_count', 'chapter_id', 'lga', 'address',
            'latitude', 'longitude', 'email',
        ]);

        return [
            ...$data,
            'name' => trim($data['name']),
            'registration_number' => strtoupper(trim((string) $this->validated('registration_number'))),
            'levels' => array_values(array_intersect(School::LEVELS, $data['levels'])),
            'phone' => PhoneNumber::normalize($this->validated('phone')),
        ];
    }

    /**
     * @return array<string, UploadedFile>
     */
    public function documents(): array
    {
        return array_filter([
            'cac_certificate' => $this->file('cac_certificate'),
            'ministry_approval' => $this->file('ministry_approval'),
            'premises_photo' => $this->file('premises_photo'),
        ], fn ($file) => $file instanceof UploadedFile);
    }
}

<?php

namespace Modules\Surveys\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    public const TYPES = ['mc', 'scale', 'number', 'text'];

    public const DEFAULT_SCALE = [1, 5];

    protected $fillable = ['survey_id', 'type', 'prompt', 'options', 'required', 'sort'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            'mc' => __('Multiple choice (pick one)'),
            'scale' => __('Rating scale'),
            'number' => __('Number'),
            'text' => __('Free text'),
        ];
    }

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /** @return HasMany<SurveyAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'question_id');
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    /**
     * The choices of a multiple-choice question.
     *
     * @return list<string>
     */
    public function choices(): array
    {
        return $this->type === 'mc' ? array_values((array) ($this->options['choices'] ?? [])) : [];
    }

    /**
     * Lowest and highest point of a rating scale.
     *
     * @return array{0: int, 1: int}
     */
    public function scaleRange(): array
    {
        return [
            (int) ($this->options['min'] ?? self::DEFAULT_SCALE[0]),
            (int) ($this->options['max'] ?? self::DEFAULT_SCALE[1]),
        ];
    }

    public function scaleLabel(string $end): ?string
    {
        $label = $this->options[$end.'_label'] ?? null;

        return filled($label) ? (string) $label : null;
    }
}

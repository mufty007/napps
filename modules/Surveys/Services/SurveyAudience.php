<?php

namespace Modules\Surveys\Services;

use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\State;
use App\Support\Scope\ActiveContext;

/**
 * Who the active role may address a survey to. National roles can target
 * everyone, a state or a chapter; state executives their state or its
 * chapters; chapter executives their own chapter.
 */
class SurveyAudience
{
    public function __construct(private readonly ActiveContext $context) {}

    /**
     * @return array<string, string> "national" | "state:{id}" | "chapter:{id}" => label
     */
    public function options(): array
    {
        $options = [];

        switch ($this->context->level()) {
            case ScopeLevel::National:
                $options['national'] = __('All member schools');
                foreach (State::query()->orderBy('name')->get(['id', 'name']) as $state) {
                    $options['state:'.$state->id] = __(':name State', ['name' => $state->name]);
                }
                foreach (Chapter::query()->with('state:id,name')->orderBy('name')->get() as $chapter) {
                    $options['chapter:'.$chapter->id] = $chapter->name.' ('.$chapter->state->name.')';
                }
                break;

            case ScopeLevel::State:
                $state = State::query()->find($this->context->stateId());
                if ($state) {
                    $options['state:'.$state->id] = __(':name State', ['name' => $state->name]);
                    foreach (Chapter::query()->where('state_id', $state->id)->orderBy('name')->get(['id', 'name']) as $chapter) {
                        $options['chapter:'.$chapter->id] = $chapter->name;
                    }
                }
                break;

            case ScopeLevel::Chapter:
                $chapter = Chapter::query()->find($this->context->chapterId());
                if ($chapter) {
                    $options['chapter:'.$chapter->id] = $chapter->name;
                }
                break;

            default:
                break;
        }

        return $options;
    }

    /**
     * Turn a validated option key into scope columns.
     *
     * @return array{scope_level: string, scope_id: int|null}
     */
    public function resolve(string $key): array
    {
        if ($key === 'national') {
            return ['scope_level' => ScopeLevel::National->value, 'scope_id' => null];
        }

        [$level, $id] = explode(':', $key, 2);

        return ['scope_level' => $level, 'scope_id' => (int) $id];
    }

    /** The option key matching a survey's stored audience. */
    public function keyFor(ScopeLevel $level, ?int $scopeId): string
    {
        return $level === ScopeLevel::National ? 'national' : $level->value.':'.$scopeId;
    }
}

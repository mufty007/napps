<?php

namespace Modules\Claims\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Claims\Http\Controllers\Concerns\RespondsToAjax;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Models\StateClaimSetting;

/** Secretariat switch for debt claims per state (CLM-2). */
class StateClaimSettingController extends Controller
{
    use RespondsToAjax;

    public function index(): View
    {
        Gate::authorize('manageSettings', ClaimCase::class);

        $settings = StateClaimSetting::query()->with('updater')->get()->keyBy('state_id');

        return view('claims::settings.states', [
            'states' => State::query()->orderBy('name')->get(),
            'settings' => $settings,
            'schoolCounts' => School::query()->toBase()->selectRaw('state_id, count(*) as total')->groupBy('state_id')->pluck('total', 'state_id'),
        ]);
    }

    public function update(Request $request, State $state): RedirectResponse|JsonResponse
    {
        Gate::authorize('manageSettings', ClaimCase::class);

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'barrister_confirmed' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $setting = StateClaimSetting::query()->firstOrNew(['state_id' => $state->getKey()]);
        $enabled = $request->boolean('enabled');
        $confirmed = $request->boolean('barrister_confirmed') || ($setting->barrister_confirmed_at !== null && ! $request->has('barrister_confirmed'));

        if ($enabled && ! $confirmed) {
            return $this->fail($request, __('A barrister must confirm the arrangements in :state before claims can be switched on.', ['state' => $state->name]));
        }

        $wasEnabled = (bool) $setting->enabled;

        $setting->fill([
            'enabled' => $enabled,
            'barrister_confirmed_at' => $confirmed ? ($setting->barrister_confirmed_at ?? now()) : null,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->getKey(),
        ])->save();

        activity('claims')->performedOn($state)->causedBy($request->user())->event('state_setting')
            ->withProperties(['state' => $state->name, 'enabled' => $enabled, 'was_enabled' => $wasEnabled])
            ->log($enabled ? "Debt claims switched on for {$state->name}" : "Debt claims switched off for {$state->name}");

        return $this->respond($request, $enabled
            ? __('Debt claims are on for :state.', ['state' => $state->name])
            : __('Debt claims are off for :state.', ['state' => $state->name]));
    }
}

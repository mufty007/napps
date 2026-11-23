<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\Money;
use App\Support\Scope\ActiveContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Fees\Models\FeeStructure;
use Modules\Fees\Rules\NairaAmount;

/**
 * FEE-1: the school's price lists. Proprietors manage them; bursars read
 * them to raise invoices.
 */
class FeeStructureController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', FeeStructure::class);

        return view('fees::structures.index', [
            'structures' => FeeStructure::query()
                ->with('items')
                ->withCount('invoices')
                ->orderByDesc('active')
                ->orderByDesc('academic_year')
                ->orderBy('name')
                ->get(),
            'canManage' => Gate::allows('create', FeeStructure::class),
        ]);
    }

    public function create(ActiveContext $context): View
    {
        Gate::authorize('create', FeeStructure::class);

        return view('fees::structures.form', [
            'structure' => new FeeStructure(['active' => true, 'academic_year' => $this->currentYear()]),
            'items' => old('items', [['name' => __('Tuition'), 'amount' => ''], ['name' => '', 'amount' => '']]),
        ]);
    }

    public function store(Request $request, ActiveContext $context): RedirectResponse
    {
        Gate::authorize('create', FeeStructure::class);

        $data = $this->validated($request);
        $school = School::query()->findOrFail($context->schoolId());

        $structure = DB::transaction(function () use ($school, $data) {
            $structure = FeeStructure::query()->create([
                'school_id' => $school->getKey(),
                'name' => $data['name'],
                'academic_year' => $data['academic_year'],
                'term' => $data['term'] ?? null,
                'active' => (bool) ($data['active'] ?? false),
            ]);

            $this->syncItems($structure, $data['items']);

            return $structure;
        });

        return redirect()->route('fees.structures.index')->with('status', __(':name saved with :count items.', ['name' => $structure->name, 'count' => count($data['items'])]));
    }

    public function edit(FeeStructure $structure): View
    {
        Gate::authorize('update', $structure);

        $structure->load('items');

        return view('fees::structures.form', [
            'structure' => $structure,
            'items' => old('items', $structure->items->map(fn ($item) => [
                'name' => $item->name,
                'amount' => Money::toNaira($item->amount_kobo),
            ])->all()),
        ]);
    }

    public function update(Request $request, FeeStructure $structure): RedirectResponse
    {
        Gate::authorize('update', $structure);

        $data = $this->validated($request);

        DB::transaction(function () use ($structure, $data) {
            $structure->update([
                'name' => $data['name'],
                'academic_year' => $data['academic_year'],
                'term' => $data['term'] ?? null,
                'active' => (bool) ($data['active'] ?? false),
            ]);

            // Invoices keep their own snapshot of the lines, so editing the price list never changes what was billed.
            $this->syncItems($structure, $data['items']);
        });

        return redirect()->route('fees.structures.index')->with('status', __(':name updated. Invoices already issued keep their original amounts.', ['name' => $structure->name]));
    }

    public function destroy(FeeStructure $structure): RedirectResponse
    {
        Gate::authorize('delete', $structure);

        if ($structure->invoices()->exists()) {
            $structure->update(['active' => false]);

            return redirect()->route('fees.structures.index')->with('status', __(':name has invoices, so it was switched off instead of deleted.', ['name' => $structure->name]));
        }

        $structure->items()->delete();
        $structure->delete();

        return redirect()->route('fees.structures.index')->with('status', __('Fee structure deleted.'));
    }

    /**
     * @return array{name: string, academic_year: string, term?: ?string, active?: mixed, items: list<array{name: string, amount: string}>}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, academic_year: string, term?: ?string, active?: mixed, items: list<array{name: string, amount: string}>} */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:16', 'regex:/^\d{4}(\/\d{4})?$/'],
            'term' => ['nullable', 'string', 'max:32'],
            'active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', new NairaAmount],
        ], [
            'academic_year.regex' => __('Use the format 2026/2027.'),
            'items.required' => __('Add at least one fee item.'),
            'items.min' => __('Add at least one fee item.'),
        ]);
    }

    /**
     * @param  list<array{name: string, amount: string}>  $items
     */
    private function syncItems(FeeStructure $structure, array $items): void
    {
        $structure->items()->delete();

        foreach (array_values($items) as $i => $item) {
            $structure->items()->create([
                'name' => trim($item['name']),
                'amount_kobo' => (int) Money::toKobo($item['amount']),
                'sort' => $i,
            ]);
        }
    }

    private function currentYear(): string
    {
        $start = now()->month >= 9 ? now()->year : now()->year - 1;

        return $start.'/'.($start + 1);
    }
}

@php
    $dt = 'text-xs font-semibold uppercase tracking-wider text-stone-500';
    $dd = 'mt-0.5 text-sm font-medium text-stone-900';
@endphp
<x-layouts.app :title="$student->fullName()">
    <x-ui.page-header :eyebrow="__('Student record')" :title="$student->fullName()" :description="$student->school?->name">
        <x-slot:actions>
            <x-ui.button :href="route('records.students.index')" variant="secondary" icon="arrow-left">{{ __('All students') }}</x-ui.button>
            @can('update', $student)
                <x-ui.button :href="route('records.students.edit', $student)" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <x-ui.card :title="__('Details')">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div><dt class="{{ $dt }}">{{ __('Status') }}</dt><dd class="mt-1"><x-ui.badge :tone="$student->status->tone()" dot>{{ $student->status->label() }}</x-ui.badge></dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Admission number') }}</dt><dd class="{{ $dd }} tabular">{{ $student->admission_no ?? '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Class') }}</dt><dd class="{{ $dd }}">{{ $student->class_name ?? '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">{{ __('Enrolled') }}</dt><dd class="{{ $dd }}">{{ $student->enrolled_at?->format('j M Y') ?? '—' }}</dd></div>
                    @if ($student->exited_at)
                        <div><dt class="{{ $dt }}">{{ __('Left') }}</dt><dd class="{{ $dd }}">{{ $student->exited_at->format('j M Y') }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Guardian')">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div><dt class="{{ $dt }}">{{ __('Name') }}</dt><dd class="{{ $dd }}">{{ $student->guardian_name }}</dd></div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Phone') }}</dt>
                        <dd class="{{ $dd }} tabular">
                            @if ($student->guardian_phone)
                                <a href="tel:{{ $student->guardian_phone }}" class="inline-flex min-h-11 items-center text-brand-700 hover:text-brand-800">{{ \App\Support\PhoneNumber::format($student->guardian_phone) }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Parent account') }}</dt>
                        <dd class="mt-1">
                            <x-ui.badge :tone="$student->guardian ? 'success' : 'neutral'">{{ $student->guardian ? $student->guardian->name : __('Not linked') }}</x-ui.badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $dt }}">{{ __('Debtor check consent') }}</dt>
                        <dd class="mt-1"><x-ui.badge :tone="$student->debtor_check_consent ? 'brand' : 'neutral'">{{ $student->debtor_check_consent ? __('Guardian consented') : __('No consent on file') }}</x-ui.badge></dd>
                    </div>
                    @can('records.transfers.manage')
                        <div>
                            <dt class="{{ $dt }}">{{ __('Fees still owed') }}</dt>
                            <dd class="{{ $dd }} tabular">{{ $student->outstanding() }}</dd>
                        </div>
                    @endcan
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Documents')" :description="__('Results, ID and other papers. Opening a file is logged.')">
                @foreach ($documents as $collection => $group)
                    <div class="mb-4 last:mb-0">
                        <h3 class="text-sm font-semibold text-stone-800">{{ __($group['label']) }}</h3>
                        @forelse ($group['files'] as $file)
                            <div class="flex min-h-14 items-center gap-3 border-b border-stone-100 py-2 last:border-0">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-stone-50 text-stone-600 ring-1 ring-stone-200"><x-ui.icon name="file-text" size="size-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('records.students.documents.show', [$student, $file]) }}" target="_blank" rel="noopener" class="block truncate text-sm font-semibold text-brand-700 hover:text-brand-800">{{ $file->name }}</a>
                                    <p class="text-xs text-stone-500">{{ $file->created_at->format('j M Y') }} · {{ \Illuminate\Support\Number::fileSize($file->size) }}</p>
                                </div>
                                @can('update', $student)
                                    <form method="POST" action="{{ route('records.students.documents.destroy', [$student, $file]) }}" onsubmit="return confirm('{{ __('Remove this document?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex size-11 items-center justify-center rounded-xl text-stone-500 hover:bg-red-50 hover:text-red-700" aria-label="{{ __('Remove :name', ['name' => $file->name]) }}">
                                            <x-ui.icon name="trash-2" size="size-4" />
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <p class="py-2 text-sm text-stone-500">{{ __('Nothing filed yet.') }}</p>
                        @endforelse
                    </div>
                @endforeach

                @can('update', $student)
                    <details class="mt-5 border-t border-stone-100 pt-5" @if ($errors->has('file') || $errors->has('collection')) open @endif>
                        <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-4 text-sm font-semibold text-stone-800 ring-1 ring-inset ring-stone-300 hover:bg-stone-50">
                            <x-ui.icon name="upload" size="size-4" /> {{ __('Add a document') }}
                        </summary>
                        <form method="POST" action="{{ route('records.students.documents.store', $student) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                            @csrf
                            <x-ui.select name="collection" :label="__('Type')" :options="collect(\Modules\Records\Models\Student::DOCUMENTS)->map(fn ($l) => __($l))" :value="old('collection', 'results')" />
                            <x-ui.input name="title" :label="__('Title (optional)')" />
                            <div class="space-y-1.5">
                                <label for="file" class="block text-sm font-medium text-stone-800">{{ __('File') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                                <input id="file" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full text-sm text-stone-700 file:mr-3 file:min-h-11 file:rounded-xl file:border-0 file:bg-stone-100 file:px-4 file:text-sm file:font-semibold file:text-stone-800 hover:file:bg-stone-200">
                                <p class="text-sm text-stone-500">{{ __('PDF or photo, up to 10 MB.') }}</p>
                                @error('file')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <x-ui.button type="submit" icon="upload">{{ __('Upload') }}</x-ui.button>
                        </form>
                    </details>
                @endcan
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @if ($transfers->isNotEmpty())
                <x-ui.side-panel :title="__('Transfer requests')">
                    <ul class="space-y-3 text-sm">
                        @foreach ($transfers as $transfer)
                            <li class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('records.transfers.show', $transfer) }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-800">{{ $transfer->toSchool?->name ?? __('Another school') }}</a>
                                    <p class="text-xs text-stone-500">{{ $transfer->created_at->format('j M Y') }}</p>
                                </div>
                                <x-ui.badge :tone="$transfer->isOverdue() ? 'danger' : $transfer->status->tone()">{{ $transfer->isOverdue() ? __('Overdue') : $transfer->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.side-panel>
            @endif

            @can('delete', $student)
                <x-ui.side-panel :title="__('Archive record')" :description="__('Hides the pupil from the register. The record, its documents and the audit trail are kept.')">
                    <form method="POST" action="{{ route('records.students.destroy', $student) }}" onsubmit="return confirm('{{ __('Archive this record?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="secondary" icon="trash-2">{{ __('Archive') }}</x-ui.button>
                    </form>
                </x-ui.side-panel>
            @endcan
        </div>
    </div>
</x-layouts.app>

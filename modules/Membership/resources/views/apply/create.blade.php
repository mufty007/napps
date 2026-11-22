<x-layouts.app :title="__('Apply for membership')">
    <x-ui.page-header
        :eyebrow="__('Membership')"
        :title="__('Apply for NAPPS membership')"
        :description="__('Your chapter verifies the school, your state approves it, and you receive a digital certificate with a membership number. We will text you at each step.')"
    />

    @if ($limitReached)
        <x-ui.alert tone="warning" :title="__('You have applications waiting')" class="mb-6">
            {{ __('Wait for a decision on your current applications before adding another school.') }}
        </x-ui.alert>
    @endif

    @php $maxMb = (int) round(config('napps.membership.document_max_kb') / 1024); @endphp

    <form
        method="POST"
        action="{{ route('membership.apply.store') }}"
        enctype="multipart/form-data"
        class="space-y-6"
        x-data="{
            chapters: @js($chapters->map->only(['id', 'state_id', 'name'])->values()),
            stateId: @js((string) old('state_id', '')),
            chapterId: @js((string) old('chapter_id', '')),
            locating: false,
            locationError: '',
            get options() { return this.chapters.filter(c => String(c.state_id) === this.stateId) },
            locate() {
                if (! navigator.geolocation) { this.locationError = @js(__('This phone or browser cannot share its location.')); return }
                this.locating = true; this.locationError = ''
                navigator.geolocation.getCurrentPosition(
                    (p) => { this.$refs.lat.value = p.coords.latitude.toFixed(6); this.$refs.lng.value = p.coords.longitude.toFixed(6); this.locating = false },
                    () => { this.locationError = @js(__('We could not get your location. Allow location access, or leave it empty.')); this.locating = false },
                    { enableHighAccuracy: true, timeout: 15000 },
                )
            },
        }"
    >
        @csrf

        <x-ui.card :title="__('School')" :description="__('As registered with the Corporate Affairs Commission and the state ministry.')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="name" :label="__('School name')" class="sm:col-span-2" required autofocus />
                <x-ui.input name="registration_number" :label="__('CAC / registration number')" placeholder="RC 1234567" required />
                <x-ui.input name="ministry_approval_number" :label="__('State ministry approval number (optional)')" />

                <fieldset class="sm:col-span-2">
                    <legend class="text-sm font-medium text-stone-800">{{ __('Levels taught') }} <span class="text-red-600" aria-hidden="true">*</span></legend>
                    <div class="mt-1 flex flex-wrap gap-x-6">
                        @foreach (\App\Models\School::LEVELS as $level)
                            <x-ui.checkbox name="levels[]" :id="'level-'.$level" :value="$level" :label="__(ucfirst($level))" :checked="in_array($level, old('levels', []), true)" />
                        @endforeach
                    </div>
                    @error('levels')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </fieldset>

                <x-ui.input name="pupil_count" type="number" inputmode="numeric" min="1" :label="__('Number of pupils')" required />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Location')" :description="__('This decides which chapter verifies your school.')">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label for="state_id" class="block text-sm font-medium text-stone-800">{{ __('State') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="state_id" name="state_id" required x-model="stateId" x-on:change="chapterId = ''"
                        @error('state_id') aria-invalid="true" aria-describedby="state_id-error" @enderror
                        class="block min-h-11 w-full rounded-xl border-0 py-2.5 pl-3.5 pr-10 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">
                        <option value="">{{ __('Choose a state') }}</option>
                        @foreach ($states as $state)
                            <option value="{{ $state->id }}" @selected((string) old('state_id') === (string) $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                    @error('state_id')<p id="state_id-error" class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-1.5">
                    <label for="chapter_id" class="block text-sm font-medium text-stone-800">{{ __('Chapter') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="chapter_id" name="chapter_id" required x-model="chapterId" x-bind:disabled="! stateId"
                        @error('chapter_id') aria-invalid="true" aria-describedby="chapter_id-error" @enderror
                        class="block min-h-11 w-full rounded-xl border-0 py-2.5 pl-3.5 pr-10 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 disabled:bg-stone-50 disabled:text-stone-400 sm:text-sm">
                        <option value="">{{ __('Choose a chapter') }}</option>
                        <template x-for="chapter in options" :key="chapter.id">
                            <option :value="String(chapter.id)" x-text="chapter.name" :selected="String(chapter.id) === chapterId"></option>
                        </template>
                    </select>
                    @error('chapter_id')<p id="chapter_id-error" class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <x-ui.input name="lga" :label="__('Local government area')" required />
                <x-ui.input name="address" :label="__('Street address')" class="sm:col-span-2" autocomplete="street-address" required />

                <fieldset class="sm:col-span-2">
                    <legend class="text-sm font-medium text-stone-800">{{ __('GPS location (optional)') }}</legend>
                    <p class="text-sm text-stone-500">{{ __('Easiest from the school compound: tap the button and allow location access.') }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <x-ui.input name="latitude" :label="__('Latitude')" inputmode="decimal" x-ref="lat" />
                        <x-ui.input name="longitude" :label="__('Longitude')" inputmode="decimal" x-ref="lng" />
                        <x-ui.button variant="secondary" icon="locate-fixed" class="col-span-2 sm:col-span-1" x-on:click="locate()" x-bind:disabled="locating">
                            <span x-show="! locating">{{ __('Use my location') }}</span>
                            <span x-show="locating" x-cloak>{{ __('Locating…') }}</span>
                        </x-ui.button>
                    </div>
                    <p x-show="locationError" x-text="locationError" x-cloak class="mt-2 text-sm font-medium text-red-700" role="alert"></p>
                </fieldset>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Contact')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="phone" type="tel" inputmode="tel" :label="__('School phone number')" :value="\App\Support\PhoneNumber::format($user->phone)" required />
                <x-ui.input name="email" type="email" :label="__('School email (optional)')" />
            </div>
            <p class="mt-4 text-sm text-stone-500">{{ __('You (:name) will be the proprietor account for this school once it is approved.', ['name' => $user->name]) }}</p>
        </x-ui.card>

        <x-ui.card :title="__('Documents')" :description="__('A clear phone photo is fine. PDF, JPG, PNG or WebP, up to :size MB each.', ['size' => $maxMb])">
            <div class="grid gap-6 sm:grid-cols-2">
                @include('membership::partials.file-input', ['name' => 'cac_certificate', 'label' => __('CAC registration certificate'), 'required' => true, 'hint' => __('Required. Your chapter checks it against the number above.')])
                @include('membership::partials.file-input', ['name' => 'ministry_approval', 'label' => __('State ministry approval letter'), 'hint' => __('If your school has one.')])
                @include('membership::partials.file-input', ['name' => 'premises_photo', 'label' => __('Photo of the school premises'), 'hint' => __('Front of the school with its signboard.'), 'accept' => 'image/jpeg,image/png,image/webp'])
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.checkbox name="declaration" :label="__('I confirm these details are correct, I am the proprietor of this school (or act for them), and I agree to NAPPS processing this information under the NDPA 2023.')" required />
            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-ui.button :href="route('dashboard')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" size="lg" icon-right="send" :disabled="$limitReached">{{ __('Send application') }}</x-ui.button>
            </div>
        </x-ui.card>
    </form>
</x-layouts.app>

@php
    $mine = $message->sender_id === auth()->id();
    $photo = $message->hidden_at ? null : $message->photo();
    $caption = trim((string) $message->body);
@endphp
<li id="message-{{ $message->id }}" data-id="{{ $message->id }}" @class(['flex gap-2', 'justify-end' => $mine])>
    @unless ($mine)
        <x-ui.avatar :user="$message->sender" size="size-8" class="mt-5" />
    @endunless
    <div @class(['max-w-[80%] min-w-0', 'items-end text-right' => $mine])>
        @if (! $mine && $conversation->type !== 'direct')
            <p class="mb-0.5 px-1 text-xs font-semibold text-stone-600">{{ $message->sender?->name }}</p>
        @endif
        @if ($photo)
            @php $src = route('community.chat.photo', [$conversation, $message]); @endphp
            <a href="{{ $src }}?full=1" target="_blank" rel="noopener" @class(['block w-fit overflow-hidden rounded-2xl ring-1 ring-stone-200 bg-stone-100', 'ml-auto rounded-br-md' => $mine, 'rounded-bl-md' => ! $mine, 'mb-1' => $caption !== ''])>
                <img src="{{ $src }}" alt="{{ $caption !== '' ? $caption : __('Photo from :name', ['name' => $message->sender?->name]) }}" loading="lazy" decoding="async" class="block max-h-72 w-auto max-w-full object-cover sm:max-w-xs">
                <span class="sr-only">{{ __('Open full size') }}</span>
            </a>
        @endif
        @if ($message->hidden_at || $caption !== '')
            <div @class([
                'inline-block rounded-2xl px-3.5 py-2 text-left text-sm leading-relaxed break-words',
                'rounded-br-md bg-brand-700 text-white' => $mine,
                'rounded-bl-md bg-white text-stone-800 ring-1 ring-stone-200' => ! $mine,
            ])>
                @if ($message->hidden_at)
                    <span class="italic opacity-70">{{ __('Removed by a moderator') }}</span>
                @else
                    {{ \Modules\Community\Support\RichText::render($message->body) }}
                @endif
            </div>
        @endif
        <p class="mt-0.5 px-1 text-[0.7rem] text-stone-500">
            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('g:ia') }}</time>
            @unless ($mine || $message->hidden_at)
                · <button type="button" class="font-semibold hover:text-stone-800" x-on:click="$dispatch('report', { type: 'message', id: {{ $message->id }} })">{{ __('Report') }}</button>
            @endunless
        </p>
    </div>
</li>

/**
 * Community Alpine components. Everything degrades to plain forms without JS.
 *
 *   likeButton(liked, count)  - like a post without reloading the page
 *   chatRoom(options)         - live chat: Reverb when configured, polling
 *                               otherwise; offline outbox; typing; "Seen";
 *                               photo attachments (sent as multipart).
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', ...(options.headers ?? {}) },
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

const store = {
    get(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
    set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* private mode */ } },
};

export function registerCommunity(Alpine) {
    Alpine.data('likeButton', (liked = false, count = 0) => ({
        liked,
        count,
        async toggle(form) {
            this.liked = !this.liked;
            this.count += this.liked ? 1 : -1;
            try {
                const data = await request(form.action, { method: 'POST' });
                this.liked = data.liked;
                this.count = data.count;
            } catch {
                form.submit(); // fall back to a normal post
            }
        },
    }));

    // Post a comment or reply in place and insert the rendered result.
    Alpine.data('commentForm', () => ({
        busy: false,
        async submit(form) {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await request(form.action, { method: 'POST', body: new FormData(form) });
                document.getElementById(form.dataset.target)?.insertAdjacentHTML('beforeend', data.html);
                form.reset();
                this.$dispatch('comment-added');
            } catch {
                form.submit();
            } finally {
                this.busy = false;
            }
        },
    }));

    // Vote / close a poll in place; the server returns the re-rendered card.
    Alpine.data('pollCard', ({ editing = false } = {}) => ({
        editing,
        busy: false,
        error: '',
        async submit(form) {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.error = Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'Something went wrong.';
                    return;
                }
                (form.closest('[data-poll]') ?? this.$root).outerHTML = data.html;
            } catch {
                form.submit();
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('chatRoom', (options) => ({
        lastId: options.lastId,
        seenUpTo: options.seenUpTo,
        draft: store.get(`napps.chat.draft.${options.id}`, ''),
        outbox: store.get(`napps.chat.outbox.${options.id}`, []),
        sending: false,
        photo: null,
        photoUrl: '',
        photoName: '',
        error: '',
        whoIsTyping: '',
        typingTimer: null,
        lastTypedAt: 0,
        channel: null,

        get queued() { return this.outbox.length; },

        get seenLabel() {
            const mine = [...this.$refs.list.querySelectorAll('li.justify-end[data-id]')];
            const last = mine.length ? Number(mine[mine.length - 1].dataset.id) : null;
            if (last === null || this.seenUpTo === null || this.seenUpTo === undefined) return '';
            return this.seenUpTo >= last ? 'Seen' : '';
        },

        init() {
            this.scrollDown(true);
            this.$watch('draft', (value) => store.set(`napps.chat.draft.${options.id}`, value));
            window.addEventListener('online', () => this.flush());
            if (this.outbox.length) this.flush();

            // Poll while the tab is visible (slower when hidden). Reverb, when
            // configured, makes updates instant; polling stays as a safety net.
            const tick = () => { this.fetchNew(); this.timer = setTimeout(tick, document.hidden ? 20000 : (this.channel ? 15000 : 4000)); };
            this.timer = setTimeout(tick, 4000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) this.fetchNew(); });

            if (options.realtime && window.connectEcho) {
                window.connectEcho().then((echo) => {
                    this.channel = echo.private(`community.conversation.${options.id}`)
                        .listen('.message.posted', () => this.fetchNew())
                        .listenForWhisper('typing', (e) => this.showTyping(e.name));
                }).catch(() => {});
            }
        },

        destroy() { clearTimeout(this.timer); },

        nearBottom() {
            const s = this.$refs.scroller;
            return s.scrollHeight - s.scrollTop - s.clientHeight < 120;
        },

        scrollDown(force = false) {
            if (force || this.nearBottom()) this.$nextTick(() => { this.$refs.scroller.scrollTop = this.$refs.scroller.scrollHeight; });
        },

        append(html) {
            this.$refs.list.querySelector('[data-empty]')?.remove();
            const stick = this.nearBottom();
            const template = document.createElement('template');
            template.innerHTML = html.trim();
            template.content.querySelectorAll('li[data-id]').forEach((li) => {
                if (!this.$refs.list.querySelector(`li[data-id="${li.dataset.id}"]`)) this.$refs.list.appendChild(li);
            });
            if (stick) this.scrollDown(true);
        },

        async fetchNew() {
            try {
                const data = await request(`${options.pollUrl}?after=${this.lastId}`);
                if (data.html) this.append(data.html);
                this.lastId = Math.max(this.lastId, data.last_id ?? 0);
                this.seenUpTo = data.seen_up_to;
            } catch { /* offline: try again on the next tick */ }
        },

        pickPhoto(event) {
            const file = event.target.files?.[0];
            this.error = '';
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.error = options.messages.notImage;
            } else if (file.size > options.photoMaxKb * 1024) {
                this.error = options.messages.tooBig;
            } else {
                this.clearPhoto(false);
                this.photo = file;
                this.photoName = file.name;
                this.photoUrl = URL.createObjectURL(file);
                this.$refs.input.focus();
                return;
            }
            event.target.value = '';
        },

        clearPhoto(resetInput = true) {
            if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
            this.photo = null;
            this.photoUrl = '';
            this.photoName = '';
            if (resetInput && this.$refs.photo) this.$refs.photo.value = '';
        },

        async send() {
            const body = this.draft.trim();
            if ((!body && !this.photo) || this.sending) return;
            this.sending = true;
            this.error = '';

            // Photos go straight up (too big for the offline outbox).
            if (this.photo) {
                const form = new FormData();
                form.append('body', body);
                form.append('photo', this.photo);
                try {
                    await this.post(form);
                    this.draft = '';
                    this.clearPhoto();
                } catch {
                    this.error = options.messages.failed;
                } finally {
                    this.sending = false;
                }
                return;
            }

            this.draft = '';
            try {
                await this.post(body);
            } catch {
                this.outbox.push(body);
                store.set(`napps.chat.outbox.${options.id}`, this.outbox);
            } finally {
                this.sending = false;
                this.$refs.input.focus();
            }
        },

        async post(payload) {
            const multipart = payload instanceof FormData;
            const data = await request(options.sendUrl, {
                method: 'POST',
                headers: { ...(multipart ? {} : { 'Content-Type': 'application/json' }), 'X-Socket-ID': window.Echo?.socketId?.() ?? '' },
                body: multipart ? payload : JSON.stringify({ body: payload }),
            });
            this.append(data.html);
            this.lastId = Math.max(this.lastId, data.id);
            this.scrollDown(true);
        },

        async flush() {
            while (this.outbox.length && navigator.onLine !== false) {
                try {
                    await this.post(this.outbox[0]);
                    this.outbox.shift();
                    store.set(`napps.chat.outbox.${options.id}`, this.outbox);
                } catch { break; }
            }
        },

        typed() {
            if (!options.typing || !this.channel || Date.now() - this.lastTypedAt < 2500) return;
            this.lastTypedAt = Date.now();
            this.channel.whisper('typing', { name: document.querySelector('[data-user-name]')?.dataset.userName ?? 'Someone' });
        },

        showTyping(name) {
            this.whoIsTyping = name;
            clearTimeout(this.typingTimer);
            this.typingTimer = setTimeout(() => { this.whoIsTyping = ''; }, 3500);
        },
    }));
}

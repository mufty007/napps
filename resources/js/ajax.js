/**
 * Shared Alpine + fetch helpers for in-place updates (shell, lists, toggles).
 * Forms still work without JS — callers should fall back to form.submit().
 */

export const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function ajax(url, options = {}) {
    const headers = {
        Accept: options.accept ?? 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers ?? {}),
    };

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
    });

    if (!response.ok) {
        let message = `HTTP ${response.status}`;
        try {
            const data = await response.clone().json();
            message = Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? message;
        } catch {
            /* ignore */
        }
        const error = new Error(message);
        error.status = response.status;
        error.response = response;
        throw error;
    }

    const type = response.headers.get('content-type') ?? '';
    if (type.includes('application/json')) {
        return response.json();
    }

    return { html: await response.text(), response };
}

export function registerAjax(Alpine) {
    Alpine.data('ajaxForm', (opts = {}) => ({
        busy: false,
        async submit(form) {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await ajax(form.action, {
                    method: (form.method || 'POST').toUpperCase(),
                    body: new FormData(form),
                });
                if (opts.onSuccess) opts.onSuccess.call(this, data, form);
                else if (data.redirect) window.location = data.redirect;
                else if (data.reload) window.location.reload();
                else if (data.status && window.toast) window.toast(data.status);
            } catch {
                form.submit();
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('ajaxFilter', (opts = {}) => ({
        busy: false,
        timer: null,
        async load(url) {
            this.busy = true;
            try {
                const data = await ajax(url, { accept: 'text/html, application/json' });
                const target = document.querySelector(opts.target ?? '[data-ajax-results]');
                if (target && data.html) {
                    const wrap = document.createElement('div');
                    wrap.innerHTML = data.html.trim();
                    const next = wrap.firstElementChild ?? wrap;
                    target.replaceWith(next);
                }
                if (opts.pushState !== false && window.history?.replaceState) {
                    window.history.replaceState({}, '', url);
                }
            } catch {
                window.location = url;
            } finally {
                this.busy = false;
            }
        },
        debounce(url, ms = 280) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.load(url), ms);
        },
        fromForm(form) {
            const url = new URL(form.action || window.location.href, window.location.origin);
            new FormData(form).forEach((value, key) => {
                if (value === '' || value === null) url.searchParams.delete(key);
                else url.searchParams.set(key, value);
            });
            this.load(url.toString());
        },
    }));

    Alpine.data('ajaxToggle', () => ({
        busy: false,
        async run(form) {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await ajax(form.action, { method: 'POST', body: new FormData(form) });
                if (data.html && form.dataset.target) {
                    document.querySelector(form.dataset.target)?.replaceWith(
                        document.createRange().createContextualFragment(data.html).firstElementChild,
                    );
                }
                if (data.status && window.toast) window.toast(data.status);
                if (data.remove) document.querySelector(data.remove)?.remove();
                this.$dispatch('ajax-toggled', data);
            } catch {
                form.submit();
            } finally {
                this.busy = false;
            }
        },
    }));
}

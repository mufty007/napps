/**
 * Toast notifications: Alpine.store('toasts').push('Saved', 'success')
 * or window.toast('Saved'). Rendered by <x-ui.toaster />.
 */
export function registerToasts(Alpine) {
    Alpine.store('toasts', {
        items: [],

        push(message, tone = 'success', timeout = 4500) {
            const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
            this.items.push({ id, message, tone });

            if (timeout) setTimeout(() => this.dismiss(id), timeout);
        },

        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });

    window.toast = (message, tone) => Alpine.store('toasts').push(message, tone);
}

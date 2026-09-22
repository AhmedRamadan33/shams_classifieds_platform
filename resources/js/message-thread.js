/**
 * The message thread (resources/views/messages/show.blade.php): submits replies with fetch so the
 * page does not reload, and polls for new messages every few seconds (GET /messages/{id}/poll).
 */
export default (config) => ({
    pollUrl: config.pollUrl,
    storeUrl: config.storeUrl,
    lastId: config.lastId,
    body: '',
    sending: false,
    error: '',
    timer: null,

    init() {
        this.timer = setInterval(() => this.poll(), 4000);
        this.$el.querySelector('[data-messages]')?.scrollTo(0, 999999);
        window.addEventListener('beforeunload', () => clearInterval(this.timer));
    },

    csrfHeaders(extra = {}) {
        return {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...extra,
        };
    },

    async poll() {
        try {
            const response = await fetch(`${this.pollUrl}?after=${this.lastId}`, { headers: this.csrfHeaders() });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.messages.length) {
                data.messages.forEach((message) => this.appendMessage(message));
                this.lastId = data.messages.at(-1).id;
            }
        } catch (e) {
            // a missed poll is not worth surfacing; the next tick tries again
        }
    },

    async send() {
        const text = this.body.trim();

        if (!text || this.sending) {
            return;
        }

        this.sending = true;
        this.error = '';

        try {
            const response = await fetch(this.storeUrl, {
                method: 'POST',
                headers: this.csrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ body: text }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.error = data.errors?.body?.[0] ?? data.message ?? '';

                return;
            }

            if (!response.ok) {
                throw new Error('send failed');
            }

            this.body = '';
            await this.poll();
        } catch (e) {
            this.error = this.msgFailed;
        } finally {
            this.sending = false;
        }
    },

    appendMessage(message) {
        const list = this.$refs.list;
        const row = document.createElement('div');
        row.className = message.mine ? 'flex justify-start' : 'flex justify-end';
        row.innerHTML = `<div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm ${message.mine ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-900'}">
            <p class="whitespace-pre-wrap break-words"></p>
            <p class="mt-1 text-xs opacity-70"></p>
        </div>`;
        row.querySelector('p').textContent = message.body;
        row.querySelectorAll('p')[1].textContent = message.time;
        list.appendChild(row);
        list.closest('[data-messages]')?.scrollTo({ top: 999999, behavior: 'smooth' });
    },
});

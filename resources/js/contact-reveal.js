export default (config) => ({
    url: config.url,
    phone: null,
    whatsappUrl: null,
    loading: false,
    failed: false,

    async request(channel) {
        const response = await fetch(this.url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ channel }),
            keepalive: true,
        });

        if (!response.ok) {
            throw new Error('contact request failed');
        }

        return response.json();
    },

    async reveal() {
        if (this.loading || this.phone) {
            return;
        }

        this.loading = true;
        this.failed = false;

        try {
            const data = await this.request('phone');
            this.phone = data.phone;
            this.whatsappUrl = data.whatsapp_url;
        } catch (error) {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    trackWhatsapp() {
        this.request('whatsapp').catch(() => {});
    },
});

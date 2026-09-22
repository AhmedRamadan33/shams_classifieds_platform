/**
 * "إظهار الرقم" on the listing page. The advertiser's number is not in the page HTML: it is
 * fetched on demand from POST /ad/{listing}/contact (which throttles and records the click).
 */
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

    // Called when the WhatsApp link is clicked: records the click without blocking the navigation.
    trackWhatsapp() {
        this.request('whatsapp').catch(() => {});
    },
});

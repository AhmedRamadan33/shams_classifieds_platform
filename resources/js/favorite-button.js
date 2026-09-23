export default (config) => ({
    favorited: config.favorited,
    busy: false,

    async toggle() {
        if (this.busy) {
            return;
        }

        const wanted = !this.favorited;
        this.favorited = wanted;
        this.busy = true;

        try {
            const response = await fetch(config.url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ favorite: wanted }),
            });

            if (response.status === 401 || response.status === 419) {
                window.location.href = config.loginUrl;

                return;
            }

            if (!response.ok) {
                throw new Error('favorite request failed');
            }

            this.favorited = (await response.json()).favorited;
        } catch (error) {
            this.favorited = !wanted;
        } finally {
            this.busy = false;
        }
    },
});

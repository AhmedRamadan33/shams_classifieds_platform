// Tajawal, self-hosted through @fontsource/tajawal (Arabic + Latin digits/letters).
import '@fontsource/tajawal/arabic-400.css';
import '@fontsource/tajawal/arabic-500.css';
import '@fontsource/tajawal/arabic-700.css';
import '@fontsource/tajawal/latin-400.css';
import '@fontsource/tajawal/latin-500.css';
import '@fontsource/tajawal/latin-700.css';

import './bootstrap';

import Alpine from 'alpinejs';
import contactReveal from './contact-reveal';
import favoriteButton from './favorite-button';
import listingForm from './listing-form';
import messageThread from './message-thread';

window.Alpine = Alpine;

Alpine.data('listingForm', listingForm);
Alpine.data('contactReveal', contactReveal);
Alpine.data('favoriteButton', favoriteButton);
Alpine.data('messageThread', messageThread);

Alpine.start();

// PWA: register the service worker (public/sw.js) so the site is installable and previously
// visited pages work offline. Silently skipped where unsupported; never blocks page load.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

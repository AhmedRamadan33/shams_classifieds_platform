@if (config('services.captcha.driver') === 'turnstile' && filled(config('services.turnstile.site_key')))
    <div {{ $attributes->class('min-h-[65px]') }}>
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-language="ar" data-theme="light"></div>
    </div>
    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
@endif

<div class="pointer-events-none absolute -start-[9999px] top-0 h-0 w-0 overflow-hidden" aria-hidden="true">
    <label for="{{ config('classifieds.honeypot_field') }}">{{ __('app.security.honeypot_label') }}</label>
    <input type="text" id="{{ config('classifieds.honeypot_field') }}" name="{{ config('classifieds.honeypot_field') }}"
           value="" tabindex="-1" autocomplete="off">
</div>

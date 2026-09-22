<x-app-layout :title="__('app.profile.title')" robots="noindex,nofollow">
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.profile.title') }}</h1>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ __('app.profile.info_title') }}</h2>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <div class="flex items-center gap-4">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" width="80" height="80" class="h-20 w-20 rounded-full object-cover">
                    @else
                        <span class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-100 text-2xl font-bold text-brand-800">{{ mb_substr($user->name, 0, 1) }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <label for="avatar" class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.profile_avatar.label') }}</label>
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-bold file:text-brand-800 hover:file:bg-brand-100">
                        <p class="mt-1 text-xs text-slate-500">{{ __('app.profile_avatar.hint') }}</p>
                        @error('avatar')
                            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                        @enderror

                        @if ($user->avatar)
                            <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="remove_avatar" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                                {{ __('app.profile_avatar.remove') }}
                            </label>
                        @endif
                    </div>
                </div>

                <x-input name="name" :label="__('app.auth.name')" :value="$user->name" autocomplete="name" required />

                <div>
                    <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.auth.phone') }}</span>
                    <p dir="ltr" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-end text-sm text-slate-700">{{ $user->phone }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('app.profile.phone_locked') }}</p>
                </div>

                <x-input name="email" type="email" :label="__('app.profile.email')" :hint="__('app.profile.email_hint')" :value="$user->email" autocomplete="email" ltr />

                <fieldset class="rounded-lg border border-slate-200 p-4">
                    <legend class="px-1 text-sm font-bold text-slate-700">{{ __('app.messages.preferences_title') }}</legend>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="notify_email" value="1" @checked(old('notify_email', $user->notify_email)) class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                            {{ __('app.messages.notify_email') }}
                        </label>
                        @error('notify_email')
                            <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
                        @enderror
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="notify_whatsapp" value="1" @checked(old('notify_whatsapp', $user->notify_whatsapp)) class="rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                            {{ __('app.messages.notify_whatsapp') }}
                        </label>
                    </div>
                </fieldset>

                <x-button type="submit">{{ __('app.save') }}</x-button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ __('app.profile.password_title') }}</h2>

            <form method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-input name="current_password" type="password" :label="__('app.profile.current_password')" autocomplete="current-password" required />
                <x-input name="password" type="password" :label="__('app.auth.new_password')" :hint="__('app.auth.password_hint')" autocomplete="new-password" required />
                <x-input name="password_confirmation" type="password" :label="__('app.auth.password_confirmation')" autocomplete="new-password" required />

                <x-button type="submit">{{ __('app.profile.change_password') }}</x-button>
            </form>
        </section>
    </div>
</x-app-layout>

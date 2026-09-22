<x-app-layout :title="$page->title" :description="\Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $page->body), 155)">
    <article class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <x-breadcrumbs :items="[
            ['label' => __('app.nav.home'), 'url' => route('home')],
            ['label' => $page->title, 'url' => null],
        ]" />

        <h1 class="mt-4 text-3xl font-bold text-slate-900">{{ $page->title }}</h1>
        <x-multiline :text="$page->body" class="mt-6 break-words rounded-2xl border border-slate-200 bg-white p-6 leading-8 text-slate-800" />
    </article>
</x-app-layout>

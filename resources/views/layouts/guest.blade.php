<x-app-layout :title="$title" :description="$description" :canonical="$canonical" :robots="$robots" :image="$image" :og-type="$ogType" :json-ld="$jsonLd">
    <div class="mx-auto w-full max-w-md px-4 py-10 sm:py-14">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>

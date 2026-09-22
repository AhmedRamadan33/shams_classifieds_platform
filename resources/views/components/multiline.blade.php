@props(['text'])
{{-- Multi-line user text: every character is escaped by e() first, and only the <br> tags added by nl2br() are trusted. --}}
<div {{ $attributes }}>{{ new \Illuminate\Support\HtmlString(nl2br(e((string) $text))) }}</div>

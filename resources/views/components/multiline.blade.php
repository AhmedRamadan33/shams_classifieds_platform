@props(['text'])
<div {{ $attributes }}>{{ new \Illuminate\Support\HtmlString(nl2br(e((string) $text))) }}</div>

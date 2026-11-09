@props(['post', 'excerpt' => ''])
{{ $post->feed->name }} — {{ $post->title }}
{{ $post->published_on->translatedFormat(__('l j F Y')) }}@if ($post->user) · {{ $post->user->name }}@endif

@if ($excerpt !== '')

{{ $excerpt }}
@endif

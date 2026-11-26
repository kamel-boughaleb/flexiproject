@props(['posts'])
@foreach ($posts as $item)
- {{ $item['post']->title }} ({{ $item['post']->feed->name }}@if ($item['post']->user) · {{ $item['post']->user->name }}@endif)
  {{ $item['post']->url() }}
@endforeach

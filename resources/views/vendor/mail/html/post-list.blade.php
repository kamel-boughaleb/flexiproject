@props(['posts'])
<table class="task-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($posts as $item)
@php($post = $item['post'])
<tr>
<td class="task-row post-row" style="border-left-color: {{ $post->feed->color ?? '#d10553' }};">
<a href="{{ $post->url() }}" class="task-title" target="_blank" rel="noopener">{{ $post->title }}</a>
<span class="task-project">{{ $post->feed->name }}@if ($post->user) · {{ $post->user->name }}@endif · {{ $post->published_on->translatedFormat(__('D j M')) }}</span>
@if ($item['excerpt'] !== '')
<span class="post-row-excerpt">{{ $item['excerpt'] }}</span>
@endif
</td>
</tr>
@endforeach
</table>

@props(['post', 'excerpt' => ''])
<table class="task-card" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="task-card-content">
<span class="task-card-project">{{ $post->feed->name }}</span>
<span class="task-card-title">{{ $post->title }}</span>
<span class="task-card-due">{{ $post->published_on->translatedFormat(__('l j F Y')) }}@if ($post->user) · {{ $post->user->name }}@endif</span>
@if ($excerpt !== '')
<span class="post-card-excerpt">{!! nl2br(e($excerpt)) !!}</span>
@endif
@if ($post->attachments->isNotEmpty())
<span class="task-card-due">📎 {{ trans_choice(':count pièce jointe|:count pièces jointes', $post->attachments->count()) }}</span>
@endif
</td>
</tr>
</table>

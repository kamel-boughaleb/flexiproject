@props(['tasks', 'today'])
@php($todayDate = \Illuminate\Support\Carbon::parse($today))
<table class="task-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($tasks as $task)
@php($due = $task->due_date)
@php($state = match (true) { $due === null => 'none', $due->toDateString() < $today => 'overdue', $due->toDateString() === $today => 'today', default => 'upcoming' })
<tr>
<td class="task-row task-{{ $state }}">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="task-main">
<a href="{{ \App\Notifications\TaskNotification::taskUrl($task) }}" class="task-title" target="_blank" rel="noopener">{{ $task->title }}</a>
<span class="task-project">{{ $task->project->name }}</span>
</td>
<td class="task-due" align="right">
@if ($due === null)
<span class="task-date">—</span>
@else
<span class="task-date">{{ $due->translatedFormat($due->year === $todayDate->year ? __('D j M') : __('D j M Y')) }}</span>
@if ($state === 'overdue')
<span class="task-badge">{{ __(':count j de retard', ['count' => (int) $due->diffInDays($todayDate)]) }}</span>
@elseif ($state === 'today')
<span class="task-badge">{{ __('Aujourd’hui') }}</span>
@endif
@endif
</td>
</tr>
</table>
</td>
</tr>
@endforeach
</table>

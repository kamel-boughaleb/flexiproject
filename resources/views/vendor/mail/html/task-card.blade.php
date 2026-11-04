@props(['task'])
<table class="task-card" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="task-card-content">
<span class="task-card-project">{{ $task->project->name }}</span>
<span class="task-card-title">{{ $task->title }}</span>
@if ($task->due_date)
<span class="task-card-due {{ ! $task->completed && $task->due_date->toDateString() < now(\App\Console\Commands\SendDailyDigest::TIMEZONE)->toDateString() ? 'is-overdue' : '' }}">{{ __('Échéance') }} : {{ $task->due_date->translatedFormat(__('l j F Y')) }}</span>
@endif
</td>
</tr>
</table>

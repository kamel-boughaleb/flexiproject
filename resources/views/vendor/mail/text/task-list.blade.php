@props(['tasks', 'today'])
@foreach ($tasks as $task)
- {{ $task->due_date && $task->due_date->toDateString() < $today ? '['.__('En retard').'] ' : '' }}{{ $task->title }} ({{ $task->project->name }}) : {{ $task->due_date?->translatedFormat(__('D j M Y')) ?? '—' }}
  {{ \App\Notifications\TaskNotification::taskUrl($task) }}
@endforeach

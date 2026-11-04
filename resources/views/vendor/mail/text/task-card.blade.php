@props(['task'])
{{ $task->project->name }} / {{ $task->title }}@if ($task->due_date) ({{ __('Échéance') }} : {{ $task->due_date->translatedFormat(__('l j F Y')) }})@endif

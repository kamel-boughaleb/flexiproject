<x-mail::message>
# {{ __('Bonjour :name,', ['name' => $name]) }}

{!! $total > 1 ? __('Vous avez **:count tâches en cours**. Voici votre point du jour.', ['count' => $total]) : __('Vous avez **:count tâche en cours**. Voici votre point du jour.', ['count' => $total]) !!}

@foreach ($groups as $title => $tasks)
## {{ __($title) }} ({{ $tasks->count() }})

<x-mail::table>
| {{ __('Tâche') }} | {{ __('Projet') }} | {{ __('Échéance') }} |
|:------|:-------|:---------|
@foreach ($tasks as $task)
| [{{ str_replace('|', '-', $task->title) }}]({{ \App\Notifications\TaskNotification::taskUrl($task) }}) | {{ str_replace('|', '-', $task->project->name) }} | {{ $task->due_date?->translatedFormat(__('D j M')) ?? '—' }} |
@endforeach
</x-mail::table>

@endforeach
<x-mail::button :url="route('my-tasks')">
{{ __('Voir toutes mes tâches') }}
</x-mail::button>

{{ __('Bonne journée !') }}<br>
{{ config('app.name') }}

<x-slot:subcopy>
{{ __('Vous recevez ce récapitulatif chaque matin à 8 h.') }} [{{ __('Régler mes notifications') }}]({{ route('notifications.settings') }})
</x-slot:subcopy>
</x-mail::message>

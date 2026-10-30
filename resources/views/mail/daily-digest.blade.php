<x-mail::message>
# Bonjour {{ $name }},

Vous avez **{{ $total }} {{ $total > 1 ? 'tâches en cours' : 'tâche en cours' }}**. Voici votre point du jour.

@foreach ($groups as $title => $tasks)
## {{ $title }} ({{ $tasks->count() }})

<x-mail::table>
| Tâche | Projet | Échéance |
|:------|:-------|:---------|
@foreach ($tasks as $task)
| [{{ str_replace('|', '-', $task->title) }}]({{ \App\Notifications\TaskNotification::taskUrl($task) }}) | {{ str_replace('|', '-', $task->project->name) }} | {{ $task->due_date?->translatedFormat('D j M') ?? '—' }} |
@endforeach
</x-mail::table>

@endforeach
<x-mail::button :url="route('my-tasks')">
Voir toutes mes tâches
</x-mail::button>

Bonne journée !<br>
{{ config('app.name') }}

<x-slot:subcopy>
Vous recevez ce récapitulatif chaque matin à 8 h. [Régler mes notifications]({{ route('notifications.settings') }})
</x-slot:subcopy>
</x-mail::message>

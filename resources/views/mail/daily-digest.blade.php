<x-mail::message>
# {{ __('Bonjour :name,', ['name' => $name]) }}

{{ __('Voici votre point du jour, trié par date d’échéance.') }}

<x-mail::summary :total="$total" :overdue="$overdue" :today="$dueToday" />

<x-mail::task-list :tasks="$tasks" :today="$today" />

<x-mail::button :url="route('my-tasks')">
{{ __('Voir toutes mes tâches') }}
</x-mail::button>

{{ __('Bonne journée !') }}

<x-slot:subcopy>
{{ __('Vous recevez ce récapitulatif chaque matin à 8 h.') }} [{{ __('Régler mes notifications') }}]({{ route('notifications.settings') }})
</x-slot:subcopy>
</x-mail::message>

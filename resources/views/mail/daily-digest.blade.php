<x-mail::message>
# {{ __('Bonjour :name,', ['name' => $name]) }}

{{ __('Voici votre récap du jour.') }}

<x-mail::summary :posts="$posts->count()" :overdue="$overdue->count()" :in-progress="$inProgress->count()" />

@if ($posts->isNotEmpty())
<x-mail::section-title>{{ __('Nouvelles actualités') }}</x-mail::section-title>

<x-mail::post-list :posts="$posts" />
@endif

@if ($overdue->isNotEmpty())
<x-mail::section-title :warning="true">{{ trans_choice(':count tâche en retard|:count tâches en retard', $overdue->count()) }}</x-mail::section-title>

<x-mail::task-list :tasks="$overdue" :today="$today" />
@endif

@if ($inProgress->isNotEmpty())
<x-mail::section-title>{{ __('Tâches en cours') }}</x-mail::section-title>

<x-mail::task-list :tasks="$inProgress" :today="$today" />
@endif

<x-mail::button :url="route('my-tasks')">
{{ __('Voir toutes mes tâches') }}
</x-mail::button>

{{ __('Bonne journée !') }}

<x-slot:subcopy>
{{ __('Vous recevez ce récapitulatif chaque matin à :time.', ['time' => $time]) }} [{{ __('Régler mes notifications') }}]({{ route('notifications.settings') }})
</x-slot:subcopy>
</x-mail::message>

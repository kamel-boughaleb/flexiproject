@props(['posts', 'overdue', 'inProgress'])
{{ trans_choice('Actualité|Actualités', $posts) }} : {{ $posts }} · {{ $overdue ? '⚠️ ' : '' }}{{ __('En retard') }} : {{ $overdue }} · {{ __('En cours') }} : {{ $inProgress }}

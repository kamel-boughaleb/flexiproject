@props(['total', 'overdue', 'today'])
{{ __('En cours') }} : {{ $total }} · {{ __('En retard') }} : {{ $overdue }} · {{ __('Aujourd’hui') }} : {{ $today }}

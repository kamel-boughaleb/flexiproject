<x-mail::message>
{{-- Salutation --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Lignes d'introduction --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Fiche de la tâche (notifications de tâche) --}}
@isset($task)
<x-mail::task-card :task="$task" />
@endisset

{{-- Bouton --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Lignes de conclusion --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Formule de fin --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif

{{-- Sous-texte --}}
@if (isset($actionText) || isset($settingsUrl))
<x-slot:subcopy>
@isset($settingsUrl)
{{ __('Vous recevez cet e-mail car vous participez à cette tâche.') }} [{{ __('Régler mes notifications') }}]({{ $settingsUrl }})

@endisset
@isset($actionText)
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
@endisset
</x-slot:subcopy>
@endif
</x-mail::message>

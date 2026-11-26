@props(['posts', 'overdue', 'inProgress'])
<table class="summary" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="summary-item"><span class="summary-value">{{ $posts }}</span><span class="summary-label">{{ trans_choice('Actualité|Actualités', $posts) }}</span></td>
<td class="summary-gap"></td>
<td class="summary-item {{ $overdue ? 'summary-overdue' : '' }}"><span class="summary-value">{{ $overdue ? '⚠️ ' : '' }}{{ $overdue }}</span><span class="summary-label">{{ __('En retard') }}</span></td>
<td class="summary-gap"></td>
<td class="summary-item"><span class="summary-value">{{ $inProgress }}</span><span class="summary-label">{{ __('En cours') }}</span></td>
</tr>
</table>

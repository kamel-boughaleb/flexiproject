@props(['total', 'overdue', 'today'])
<table class="summary" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="summary-item"><span class="summary-value">{{ $total }}</span><span class="summary-label">{{ __('En cours') }}</span></td>
<td class="summary-gap"></td>
<td class="summary-item {{ $overdue ? 'summary-overdue' : '' }}"><span class="summary-value">{{ $overdue }}</span><span class="summary-label">{{ __('En retard') }}</span></td>
<td class="summary-gap"></td>
<td class="summary-item"><span class="summary-value">{{ $today }}</span><span class="summary-label">{{ __('Aujourd’hui') }}</span></td>
</tr>
</table>

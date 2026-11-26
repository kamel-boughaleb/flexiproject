@props(['warning' => false])
<table class="digest-section {{ $warning ? 'digest-section-warning' : '' }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="digest-section-title">{{ $warning ? '⚠️ ' : '' }}{{ trim(strip_tags($slot)) }}</td>
</tr>
</table>

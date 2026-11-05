@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- PNG et URL absolue : les messageries (Gmail, Outlook…) n'affichent ni le SVG ni les chemins relatifs. --}}
<img src="{{ asset('images/email-logo.png') }}" class="logo" width="164" height="40" alt="{{ config('app.name') }}">
</a>
</td>
</tr>

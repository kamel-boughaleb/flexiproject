@props(['warning' => false])
{{ $warning ? '⚠️ ' : '' }}{{ trim(strip_tags($slot)) }}

@props(['url', 'lead' => null])
<p class="secondary-link">{{ $lead }} <a href="{{ $url }}" target="_blank" rel="noopener">{{ trim($slot) }}</a></p>

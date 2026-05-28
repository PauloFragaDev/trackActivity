@props(['at', 'locale' => 'es', 'mode' => 'relative'])
@php
    if ($at instanceof \DateTimeInterface) {
        $carbon = \Carbon\CarbonImmutable::instance($at);
    } else {
        $carbon = \Carbon\CarbonImmutable::parse($at);
    }
    $tz       = config('tracker.display_timezone', 'UTC');
    $local    = $carbon->setTimezone($tz);
    $absolute = $local->locale($locale)->isoFormat('D MMM YYYY, HH:mm');
    $relative = $local->locale($locale)->diffForHumans();
    $display  = $mode === 'absolute' ? $absolute : $relative;
@endphp
{{-- Hora formateada de manera consistente en toda la app. Atributo `title`
     siempre lleva la versión absoluta para que el usuario pueda hover y
     ver la hora exacta sin tener que cambiar de vista. `mode` admite
     'relative' (default, "hace 5 min") o 'absolute' (la fecha completa). --}}
<time {{ $attributes->merge(['class' => 'text-faint cursor-help']) }}
      datetime="{{ $carbon->toIso8601String() }}"
      title="{{ $absolute }}">{{ $display }}</time>

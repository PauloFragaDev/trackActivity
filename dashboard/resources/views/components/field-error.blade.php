@props(['name'])
{{-- Error inline para un campo de formulario. Renderiza el primer error
     de `$name` (notación de Laravel: 'title', 'project_id', 'lists.0.title'…)
     en color rose, debajo del input. Si no hay error, no pinta nada. --}}
@error($name)
    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>
@enderror

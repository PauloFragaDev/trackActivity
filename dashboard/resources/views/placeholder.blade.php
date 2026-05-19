@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="card p-8 text-center">
        <h1 class="text-lg font-semibold mb-1">{{ $title }}</h1>
        <p class="text-sm text-muted">{{ $detail }}</p>
    </div>
@endsection

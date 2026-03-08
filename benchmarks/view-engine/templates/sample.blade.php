@extends('layouts.main')

@section('content')
    @include('partials.header', ['title' => $title, 'items' => $items])
    <ul>
        @foreach($items as $i => $item)
            <li @if($i % 2 == 0) class="even" @endif>{{ $item }}</li>
        @endforeach
    </ul>
@endsection
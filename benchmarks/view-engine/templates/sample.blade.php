@extends('layouts.main')

@section('content')
    @include('partials.header', ['title' => $title, 'items' => $items])
    <ul>
        @foreach($items as $i => $item)
            <li @if($i % 2 == 0) class="even" @endif>
                {{ $item }}
                <div class="extras">
                    @for ($j = 0; $j < 10; $j++)
                        <span>{{ $item . '-' . $j }}</span>
                    @endfor
                </div>
            </li>
        @endforeach
    </ul>
@endsection
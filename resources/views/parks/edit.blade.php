@extends('header')

@section('onReady')
	$('input#name').focus();
@stop

@section('content')

@if (count($errors) > 0)
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">

  {!! Former::open($url)
            ->autocomplete('off')
            ->rules(
                []
            )->addClass('col-md-12 warn-on-exit')
            ->method($method) !!}

    @include('partials.autocomplete_fix')

  {!! Former::close() !!}
</div>

@stop

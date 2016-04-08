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


    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-default">
          <div class="panel-body">
            {!! Former::text('name')->data_bind("attr { placeholder: placeholderName }") !!}
            {!! Former::text('city') !!}
            {!! Former::text('state') !!}
            {!! Former::text('website') !!}
            {!! Form::styledFile('park_image') !!}
          </div>

        </div>
      </div>
    </div>


    <center class="buttons">
          {!! Button::normal(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo(URL::to('/parks'))->large() !!}
          {!! Button::success(trans('texts.save'))->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
  	</center>

  {!! Former::close() !!}
</div>

@stop

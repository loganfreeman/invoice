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

  {!! Former::open_for_files($url)
            ->autocomplete('off')
            ->rules(
                []
            )->addClass('col-md-12 warn-on-exit')
            ->method($method) !!}

				@include('partials.autocomplete_fix')

				@if ($park)
					{!! Former::populate($park) !!}
			        {!! Former::hidden('id') !!}
				@endif


    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-default">
          <div class="panel-body">
            {!! Former::text('name') !!}
            {!! Former::text('city') !!}
            {!! Former::text('state') !!}
            {!! Former::text('website') !!}
            {!! Form::styledFile('photo_path') !!}
          </div>

        </div>
      </div>
    </div>


		{!! Former::hidden('data')->data_bind("value: ko.toJSON(model)") !!}

		<script type="text/javascript">

		function ParkModel(data){
			var self = this;
			self.mapping = {}
			if (data) {
				ko.mapping.fromJS(data, self.mapping, this);
			}
		}

		@if ($data)
      window.model = new ParkModel({!! $data !!});
    @elseif ($park)
	    window.model = new ParkModel({!! $park !!});
    @endif

		if(window.model){
			ko.applyBindings(window.model);
		}

		</script>


    <center class="buttons">
          {!! Button::normal(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo(URL::to('/parks'))->large() !!}
          {!! Button::success(trans('texts.save'))->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
  	</center>

  {!! Former::close() !!}
</div>

@stop

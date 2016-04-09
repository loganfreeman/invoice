@extends('header')

@section('head')
    @parent

    @if ($park->hasAddress())
        <style>
          #map {
            width: 100%;
            height: 200px;
            border-width: 1px;
            border-style: solid;
            border-color: #ddd;
          }
        </style>

        <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}"></script>
    @endif
@stop


@section('content')

	<div class="pull-right">
		{!! Former::open('parks/bulk')->addClass('mainForm') !!}
		<div style="display:none">
			{!! Former::text('action') !!}
			{!! Former::text('id')->value($park->id) !!}
		</div>

		@if ($park->trashed())
			{!! Button::primary(trans('texts.restore_park'))->withAttributes(['onclick' => 'onRestoreClick()']) !!}
		@else
		    {!! DropdownButton::normal(trans('texts.edit_park'))
                ->withAttributes(['class'=>'normalDropDown'])
                ->withContents([
			      ['label' => trans('texts.archive_park'), 'url' => "javascript:onArchiveClick()"],
			      ['label' => trans('texts.delete_park'), 'url' => "javascript:onDeleteClick()"],
			    ]
			  )->split() !!}

		@endif
	  {!! Former::close() !!}

	</div>


	<h2>{{ $park->getDisplayName() }}</h2>
    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">
		<div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>

            @if ($park->getCityState())
                {{ $park->getCityState() }}<br/>
            @endif
            @if ($park->country)
                {{ $park->country }}<br/>
            @endif

		  	@if ($park->website)
		  	   <p>{!! Utils::formatWebsite($park->website) !!}</p>
            @endif

		</div>


	</div>
    </div>
    </div>

    @if ($park->hasAddress())
        <div id="map"></div>
        <br/>
    @endif

	<script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('parks/' . $park->id . '/edit') }}';
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('expenses/create/' . $park->id ) }}';
		});

        // load datatable data when tab is shown and remember last tab selected
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          var target = $(e.target).attr("href") // activated tab
          target = target.substring(1);
          localStorage.setItem('park_tab', target);
          if (!loadedTabs.hasOwnProperty(target)) {
            loadedTabs[target] = true;
            window['load_' + target]();
          }
        });
        var tab = localStorage.getItem('park_tab');
        if (tab && tab != 'activity') {
            $('.nav-tabs a[href="#' + tab.replace('#', '') + '"]').tab('show');
        } else {
            //window['load_activity']();
        }
	});

	function onArchiveClick() {
		$('#action').val('archive');
		$('.mainForm').submit();
	}

	function onRestoreClick() {
		$('#action').val('restore');
		$('.mainForm').submit();
	}

	function onDeleteClick() {
		if (confirm("{!! trans('texts.are_you_sure') !!}")) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}
	}

    @if ($park->hasAddress())
        function initialize() {
            var mapCanvas = document.getElementById('map');
            var mapOptions = {
                zoom: {{ DEFAULT_MAP_ZOOM }},
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
            };

            var map = new google.maps.Map(mapCanvas, mapOptions)
            var address = "{{ "{$park->address1} {$park->address2} {$park->city} {$park->state} {$park->postal_code} " . $park->country }}";

            geocoder = new google.maps.Geocoder();
            geocoder.geocode( { 'address': address}, function(results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                  if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {
                    var result = results[0];
                    map.setCenter(result.geometry.location);

                    var infowindow = new google.maps.InfoWindow(
                        { content: '<b>'+result.formatted_address+'</b>',
                        size: new google.maps.Size(150, 50)
                    });

                    var marker = new google.maps.Marker({
                        position: result.geometry.location,
                        map: map,
                        title:address,
                    });
                    google.maps.event.addListener(marker, 'click', function() {
                        infowindow.open(map, marker);
                    });
                } else {
                    $('#map').hide();
                }
            } else {
              $('#map').hide();
          }
      });
    }

    google.maps.event.addDomListener(window, 'load', initialize);
    @endif

	</script>

@stop

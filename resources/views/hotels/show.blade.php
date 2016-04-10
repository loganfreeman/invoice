@extends('header')

@section('head')
    @parent

    @if ($hotel->hasAddress())
        <style>
          #map {
            width: 100%;
            height: 400px;
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
		{!! Former::open('hotels/bulk')->addClass('mainForm') !!}
		<div style="display:none">
			{!! Former::text('action') !!}
			{!! Former::text('id')->value($hotel->id) !!}
		</div>

		@if ($hotel->trashed())
			{!! Button::primary(trans('texts.restore_hotel'))->withAttributes(['onclick' => 'onRestoreClick()']) !!}
		@else
		    {!! DropdownButton::normal(trans('texts.edit_hotel'))
                ->withAttributes(['class'=>'normalDropDown'])
                ->withContents([
			      ['label' => trans('texts.archive_hotel'), 'url' => "javascript:onArchiveClick()"],
			      ['label' => trans('texts.delete_hotel'), 'url' => "javascript:onDeleteClick()"],
			    ]
			  )->split() !!}

		@endif
	  {!! Former::close() !!}

	</div>


	<h2>{{ $hotel->getDisplayName() }}</h2>
    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">
		<div class="col-md-9">
			<h3>{{ trans('texts.details') }}</h3>

            @if ($hotel->getCityState())
                {{ $hotel->getCityState() }}<br/>
            @endif
            @if ($hotel->country)
                {{ $hotel->country }}<br/>
            @endif

		  	@if ($hotel->website)
		  	   <p>{!! Utils::formatWebsite($hotel->website) !!}</p>
            @endif

		</div>


	</div>
    </div>
    </div>

    @if ($hotel->hasAddress())
        <div id="map"></div>
        <br/>
    @endif

	<script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('hotels/' . $hotel->id . '/edit') }}';
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('expenses/create/' . $hotel->id ) }}';
		});

        // load datatable data when tab is shown and remember last tab selected
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          var target = $(e.target).attr("href") // activated tab
          target = target.substring(1);
          localStorage.setItem('hotel_tab', target);
          if (!loadedTabs.hasOwnProperty(target)) {
            loadedTabs[target] = true;
            window['load_' + target]();
          }
        });
        var tab = localStorage.getItem('hotel_tab');
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

    @if ($hotel->hasAddress())
        function initialize() {
            var mapCanvas = document.getElementById('map');
            var mapOptions = {
                zoom: {{ DEFAULT_MAP_ZOOM }},
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
            };

            var map = new google.maps.Map(mapCanvas, mapOptions)
            var address = "{{ "{$hotel->address1} {$hotel->address2} {$hotel->city} {$hotel->state} {$hotel->postal_code} " . $hotel->country }}";

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

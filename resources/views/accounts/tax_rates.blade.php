@extends('header')

@section('content') 
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_TAX_RATES])

  {!! Former::open()->addClass('warn-on-exit') !!}
  {{ Former::populate($account) }}
  {{ Former::populateField('invoice_taxes', intval($account->invoice_taxes)) }}
  {{ Former::populateField('invoice_item_taxes', intval($account->invoice_item_taxes)) }}
  {{ Former::populateField('show_item_taxes', intval($account->show_item_taxes)) }}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.tax_settings') !!}</h3>
  </div>  
  <div class="panel-body">

      {!! Former::checkbox('invoice_taxes')
            ->text(trans('texts.enable_invoice_tax'))
            ->label('&nbsp;') !!}

      {!! Former::checkbox('invoice_item_taxes')
            ->text(trans('texts.enable_line_item_tax'))
            ->label('&nbsp;') !!}

      {!! Former::checkbox('show_item_taxes')
            ->text(trans('texts.show_line_item_tax'))
            ->label('&nbsp;') !!}

      &nbsp;

      {!! Former::select('default_tax_rate_id')
            ->style('max-width: 250px')
            ->addOption('', '')
            ->fromQuery($taxRates, function($model) { return $model->name . ': ' . $model->rate . '%'; }, 'id') !!}


      &nbsp;
      {!! Former::actions( Button::success(trans('texts.save'))->submit()->appendIcon(Icon::create('floppy-disk')) ) !!}
      {!! Former::close() !!}
  </div>
  </div>

  {!! Button::primary(trans('texts.create_tax_rate'))
        ->asLinkTo(URL::to('/tax_rates/create'))
        ->withAttributes(['class' => 'pull-right'])
        ->appendIcon(Icon::create('plus-sign')) !!}

  @include('partials.bulk_form', ['entityType' => ENTITY_TAX_RATE])

  {!! Datatable::table()   
      ->addColumn(
        trans('texts.name'),
        trans('texts.rate'),
        trans('texts.action'))
      ->setUrl(url('api/tax_rates/'))      
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)      
      ->setOptions('bAutoWidth', false)      
      ->setOptions('aoColumns', [[ "sWidth"=> "40%" ], [ "sWidth"=> "40%" ], ["sWidth"=> "20%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[2]]])
      ->render('datatable') !!}

  <script>
    window.onDatatableReady = actionListHandler;
  </script>  


@stop
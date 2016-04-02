@extends('header')

@section('head')
    @parent

    @include('money_script')
@foreach (Auth::user()->account->getFontFolders() as $font)
  <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
@endforeach
  <script src="{{ asset('pdf.built.js') }}" type="text/javascript"></script>

  <script>

    var invoiceDesigns = {!! $invoiceDesigns !!};
    var invoiceFonts = {!! $invoiceFonts !!};
    var currentInvoice = {!! $invoice !!};
    var versionsJson = {!! $versionsJson !!};
    
    function getPDFString(cb) {

        var version = $('#version').val();
        var invoice;

        if (parseInt(version)) {
            invoice = versionsJson[version];
        } else {
            invoice = currentInvoice;
        }

        invoice.image = window.accountLogo;

        var invoiceDesignId = parseInt(invoice.invoice_design_id);
        var invoiceDesign = _.findWhere(invoiceDesigns, {id: invoiceDesignId});
        if (!invoiceDesign) {
            invoiceDesign = invoiceDesigns[0];
        }
        
        generatePDF(invoice, invoiceDesign.javascript, true, cb);        
    }

    $(function() {   
      refreshPDF();
    });

  </script> 

@stop

@section('content')

    {!! Former::open()->addClass('form-inline')->onchange('refreshPDF()') !!}
    {!! Former::select('version')->options($versionsSelect)->label(trans('select_version'))->style('background-color: white !important') !!}
    {!! Button::primary(trans('texts.edit_' . $invoice->getEntityType()))->asLinkTo(URL::to('/' . $invoice->getEntityType() . 's/' . $invoice->public_id . '/edit'))->withAttributes(array('class' => 'pull-right')) !!}    
    {!! Former::close() !!}

    <br/>&nbsp;<br/>

    @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])

@stop
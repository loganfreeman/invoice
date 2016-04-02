@extends('header')

@section('head')
    @parent

    @include('money_script')
    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>

    <style type="text/css">
        textarea {
            min-height: 150px !important;
        }
    </style>

    <script type="text/javascript">
        var editors = [];
    </script>

@stop

@section('content')
    @parent
    @include('accounts.nav', ['selected' => ACCOUNT_TEMPLATES_AND_REMINDERS, 'advanced' => true])


    {!! Former::vertical_open()->addClass('warn-on-exit') !!}
    {!! Former::populate($account) !!}

    @foreach ([ENTITY_INVOICE, ENTITY_QUOTE, ENTITY_PAYMENT, REMINDER1, REMINDER2, REMINDER3] as $type)
        @foreach (['subject', 'template'] as $field)
            {!! Former::populateField("email_{$field}_{$type}", $templates[$type][$field]) !!}
        @endforeach
    @endforeach

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_templates') !!}</h3>
        </div>
        <div class="panel-body">
            <div class="row">
                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#invoice" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.invoice_email') }}</a></li>
                        <li role="presentation"><a href="#quote" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.quote_email') }}</a></li>
                        <li role="presentation"><a href="#payment" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.payment_email') }}</a></li>
                    </ul>
                    <div class="tab-content">
                        @include('accounts.template', ['field' => 'invoice', 'active' => true])
                        @include('accounts.template', ['field' => 'quote'])
                        @include('accounts.template', ['field' => 'payment'])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p>&nbsp;</p>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.reminder_emails') !!}</h3>
        </div>
        <div class="panel-body">
            <div class="row">
                <div role="tabpanel">
                    <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#reminder1" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.first_reminder') }}</a></li>
                        <li role="presentation"><a href="#reminder2" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.second_reminder') }}</a></li>
                        <li role="presentation"><a href="#reminder3" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.third_reminder') }}</a></li>
                    </ul>
                    <div class="tab-content">
                        @include('accounts.template', ['field' => 'reminder1', 'isReminder' => true, 'active' => true])
                        @include('accounts.template', ['field' => 'reminder2', 'isReminder' => true])
                        @include('accounts.template', ['field' => 'reminder3', 'isReminder' => true])
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="templateHelpModal" tabindex="-1" role="dialog" aria-labelledby="templateHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="templateHelpModalLabel">{{ trans('texts.template_help_title') }}</h4>
                </div>

                <div class="modal-body">
                    <p>{{ trans('texts.template_help_1') }}</p>
                    <ul>
                        @foreach (\App\Ninja\Mailers\ContactMailer::$variableFields as $field)
                            <li>${{ $field }}</li>
                        @endforeach
                        @if ($account->custom_client_label1)
                            <li>$customClient1</li>
                        @endif
                        @if ($account->custom_client_label2)
                            <li>$customClient2</li>
                        @endif
                        @if ($account->custom_invoice_text_label1)
                            <li>$customInvoice1</li>
                        @endif
                        @if ($account->custom_invoice_text_label2)
                            <li>$customInvoice1</li>
                        @endif
                        @if (count($account->account_gateways) > 1)
                            @foreach (\App\Models\Gateway::$paymentTypes as $type)
                                @if ($account->getGatewayByType($type))
                                    <li>${{ \App\Models\Gateway::getPaymentTypeName($type) }}Link</li>
                                    <li>${{ \App\Models\Gateway::getPaymentTypeName($type) }}Button</li>
                                @endif
                            @endforeach
                        @endif
                    </ul>
                </div>

                <div class="modal-footer" style="margin-top: 0px">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>

    @if (Auth::user()->isPro())
        <center>
            {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @else
        <script>
            $(function() {
                $('form.warn-on-exit input').prop('disabled', true);
            });
        </script>
    @endif

    {!! Former::close() !!}

    <script type="text/javascript">

        var entityTypes = ['invoice', 'quote', 'payment', 'reminder1', 'reminder2', 'reminder3'];
        var stringTypes = ['subject', 'template'];
        var templates = {!! json_encode($defaultTemplates) !!};

        function refreshPreview() {
            for (var i=0; i<entityTypes.length; i++) {
                var entityType = entityTypes[i];
                for (var j=0; j<stringTypes.length; j++) {
                    var stringType = stringTypes[j];
                    var idName = '#email_' + stringType + '_' + entityType;
                    var value = $(idName).val();
                    var previewName = '#' + entityType + '_' + stringType + '_preview';
                    $(previewName).html(processVariables(value));
                }
            }            
        }

        $(function() {
            for (var i=0; i<entityTypes.length; i++) {
                var entityType = entityTypes[i];
                for (var j=0; j<stringTypes.length; j++) {
                    var stringType = stringTypes[j];
                    var idName = '#email_' + stringType + '_' + entityType;
                    $(idName).keyup(refreshPreview);
                }
            }

            for (var i=1; i<=3; i++) {
                $('#enable_reminder' + i).bind('click', {id: i}, function(event) {
                    enableReminder(event.data.id)
                });
                enableReminder(i);
            }

            refreshPreview();
        });

        function enableReminder(id) {            
            var checked = $('#enable_reminder' + id).is(':checked');
            $('.enable-reminder' + id).attr('disabled', !checked)
        }

        function setDirectionShown(field) {
            var val = $('#field_' + field).val();
            if (val == {{ REMINDER_FIELD_INVOICE_DATE }}) {
                $('#days_after_' + field).show();
                $('#direction_' + field).hide();
            } else {
                $('#days_after_' + field).hide();
                $('#direction_' + field).show();
            }
        }

        function processVariables(str) {
            if (!str) {
                return '';
            }

            var keys = {!! json_encode(\App\Ninja\Mailers\ContactMailer::$variableFields) !!};
            var passwordHtml = "{!! $account->isPro() && $account->enable_portal_password && $account->send_portal_password?'<p>'.trans('texts.password').': 6h2NWNdw6<p>':'' !!}";
            var vals = [
                {!! json_encode($emailFooter) !!}, 
                "{{ $account->getDisplayName() }}", 
                "{{ $account->formatDate($account->getDateTime()) }}",
                "{{ $account->formatDate($account->getDateTime()) }}",
                "Client Name", 
                formatMoney(100), 
                "Contact Name", 
                "First Name",
                "0001", 
                "0001",
                passwordHtml,
                "{{ URL::to('/view/...') }}$password", 
                '{!! Form::flatButton('view_invoice', '#0b4d78') !!}$password',
                "{{ URL::to('/payment/...') }}$password", 
                '{!! Form::flatButton('pay_now', '#36c157') !!}$password',
            ];

            // Add blanks for custom values
            keys.push('customClient1', 'customClient2', 'customInvoice1', 'customInvoice2');
            vals.push('custom value', 'custom value', 'custom value', 'custom value');

            // Add any available payment method links
            @foreach (\App\Models\Gateway::$paymentTypes as $type)
                {!! "keys.push('" . \App\Models\Gateway::getPaymentTypeName($type).'Link' . "');" !!}
                {!! "vals.push('" . URL::to('/payment/...') . "');" !!}

                {!! "keys.push('" . \App\Models\Gateway::getPaymentTypeName($type).'Button' . "');" !!}
                {!! "vals.push('" . Form::flatButton('pay_now', '#36c157') . "');" !!}
            @endforeach

            var includesPasswordPlaceholder = str.indexOf('$password') != -1;
                
            for (var i=0; i<keys.length; i++) {
                var regExp = new RegExp('\\$'+keys[i], 'g');
                str = str.replace(regExp, vals[i]);
            }
                 
            if(!includesPasswordPlaceholder){
                var lastSpot = str.lastIndexOf('$password')
                str = str.slice(0, lastSpot) + str.slice(lastSpot).replace('$password', passwordHtml);
            }
            str = str.replace(/\$password/g,'');

            return str;
        }

        function resetText(section, field) {
            if (confirm('{!! trans("texts.are_you_sure") !!}')) {
                var fieldName = 'email_' + section + '_' + field;
                var value = templates[field][section];
                $('#' + fieldName).val(value);
                if (section == 'template') {
                    editors[field].setHTML(value);
                }
                refreshPreview();
            }

            return false;
        }

    </script>

@stop

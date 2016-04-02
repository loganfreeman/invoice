@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

@if ($errors->first('contacts'))
    <div class="alert alert-danger">{{ trans($errors->first('contacts')) }}</div>
@endif

<div class="row">

	{!! Former::open($url)
            ->autocomplete('off')
            ->rules(
                ['email' => 'email']
            )->addClass('col-md-12 warn-on-exit')
            ->method($method) !!}
            
    @include('partials.autocomplete_fix')

	@if ($client)
		{!! Former::populate($client) !!}
        {!! Former::hidden('public_id') !!}
	@endif

	<div class="row">
		<div class="col-md-6">


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.organization') !!}</h3>
          </div>
            <div class="panel-body">
			
			{!! Former::text('name')->data_bind("attr { placeholder: placeholderName }") !!}
			{!! Former::text('id_number') !!}
                        {!! Former::text('vat_number') !!}
                        {!! Former::text('website') !!}
			{!! Former::text('work_phone') !!}
			
			@if (Auth::user()->isPro())
				@if ($customLabel1)
					{!! Former::text('custom_value1')->label($customLabel1) !!}
				@endif
				@if ($customLabel2)
					{!! Former::text('custom_value2')->label($customLabel2) !!}
				@endif
			@endif
            </div>
            </div>

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.address') !!}</h3>
          </div>
            <div class="panel-body">
        			
			{!! Former::text('address1') !!}
			{!! Former::text('address2') !!}
			{!! Former::text('city') !!}
			{!! Former::text('state') !!}
			{!! Former::text('postal_code') !!}
			{!! Former::select('country_id')->addOption('','')
				->fromQuery($countries, 'name', 'id') !!}

        </div>
        </div>
		</div>
		<div class="col-md-6">


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.contacts') !!}</h3>
          </div>
            <div class="panel-body">

			<div data-bind='template: { foreach: contacts,
		                            beforeRemove: hideContact,
		                            afterAdd: showContact }'>
				{!! Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][public_id]'}") !!}
				{!! Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown', 
                        attr: {name: 'contacts[' + \$index() + '][first_name]'}") !!}
				{!! Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][last_name]'}") !!}
				{!! Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown', 
                        attr: {name: 'contacts[' + \$index() + '][email]', id:'email'+\$index()}") !!}
				{!! Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][phone]'}") !!}
				@if ($account->isPro() && $account->enable_portal_password)
					{!! Former::password('password')->data_bind("value: password()?'-%unchanged%-':'', valueUpdate: 'afterkeydown',
						attr: {name: 'contacts[' + \$index() + '][password]'}") !!}
			    @endif
				<div class="form-group">
					<div class="col-lg-8 col-lg-offset-4 bold">
						<span class="redlink bold" data-bind="visible: $parent.contacts().length > 1">
							{!! link_to('#', trans('texts.remove_contact').' -', array('data-bind'=>'click: $parent.removeContact')) !!}
						</span>					
						<span data-bind="visible: $index() === ($parent.contacts().length - 1)" class="pull-right greenlink bold">
							{!! link_to('#', trans('texts.add_contact').' +', array('onclick'=>'return addContact()')) !!}
						</span>
					</div>
				</div>
			</div>
            </div>
            </div>


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.additional_info') !!}</h3>
          </div>
            <div class="panel-body">
			
            {!! Former::select('currency_id')->addOption('','')
                ->placeholder($account->currency ? $account->currency->name : '')
                ->fromQuery($currencies, 'name', 'id') !!}
            {!! Former::select('language_id')->addOption('','')
                ->placeholder($account->language ? $account->language->name : '')
                ->fromQuery($languages, 'name', 'id') !!}
			{!! Former::select('payment_terms')->addOption('','')
				->fromQuery($paymentTerms, 'name', 'num_days')
                ->help(trans('texts.payment_terms_help')) !!}
			{!! Former::select('size_id')->addOption('','')
				->fromQuery($sizes, 'name', 'id') !!}
			{!! Former::select('industry_id')->addOption('','')
				->fromQuery($industries, 'name', 'id') !!}
			{!! Former::textarea('private_notes') !!}


            @if (isset($proPlanPaid))
                {!! Former::populateField('pro_plan_paid', $proPlanPaid) !!}
                {!! Former::text('pro_plan_paid')
                            ->data_date_format('yyyy-mm-dd')
                            ->addGroupClass('pro_plan_paid_date')
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
                <script type="text/javascript">
                    $(function() {
                        $('#pro_plan_paid').datepicker();
                    });
                </script>
            @endif

            </div>
            </div>

		</div>
	</div>


	{!! Former::hidden('data')->data_bind("value: ko.toJSON(model)") !!}

	<script type="text/javascript">

	$(function() {
		$('#country_id').combobox();
	});

	function ContactModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.first_name = ko.observable('');
		self.last_name = ko.observable('');
		self.email = ko.observable('');
		self.phone = ko.observable('');
		self.password = ko.observable('');

		if (data) {
			ko.mapping.fromJS(data, {}, this);
		}
	}

	function ClientModel(data) {
		var self = this;

        self.contacts = ko.observableArray();

		self.mapping = {
		    'contacts': {
		    	create: function(options) {
		    		return new ContactModel(options.data);
		    	}
		    }
		}

		if (data) {
			ko.mapping.fromJS(data, self.mapping, this);
		} else {
			self.contacts.push(new ContactModel());
		}

		self.placeholderName = ko.computed(function() {
			if (self.contacts().length == 0) return '';
			var contact = self.contacts()[0];
			if (contact.first_name() || contact.last_name()) {
				return contact.first_name() + ' ' + contact.last_name();
			} else {
				return contact.email();
			}
		});	
	}

    @if ($data)
        window.model = new ClientModel({!! $data !!});
    @else
	    window.model = new ClientModel({!! $client !!});
    @endif

	model.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
	model.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }


	ko.applyBindings(model);

	function addContact() {
		model.contacts.push(new ContactModel());
		return false;
	}

	model.removeContact = function() {
		model.contacts.remove(this);
	}


	</script>

	<center class="buttons">
    	{!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/clients/' . ($client ? $client->public_id : '')))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}
</div>
@stop

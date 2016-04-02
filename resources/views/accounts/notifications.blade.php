@extends('header')

@section('content')	
	@parent	

    @include('accounts.nav', ['selected' => ACCOUNT_NOTIFICATIONS])

	{!! Former::open()->addClass('warn-on-exit') !!}
	{{ Former::populate($account) }}
	{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
	{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
	{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}
    {{ Former::populateField('notify_approved', intval(Auth::user()->notify_approved)) }}

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.email_notifications') !!}</h3>
      </div>
        <div class="panel-body">
    	{!! Former::checkbox('notify_sent')->label('&nbsp;')->text(trans('texts.email_sent')) !!}
    	{!! Former::checkbox('notify_viewed')->label('&nbsp;')->text(trans('texts.email_viewed')) !!}
        {!! Former::checkbox('notify_paid')->label('&nbsp;')->text(trans('texts.email_paid')) !!}    
        {!! Former::checkbox('notify_approved')->label('&nbsp;')->text(trans('texts.email_approved')) !!}
        </div>
    </div>


    <!--
    {!! Former::legend(trans('texts.site_updates')) !!}

    <div class="form-group">
        <label for="invoice_terms" class="control-label col-lg-4 col-sm-4"></label>
        <div class="col-lg-8 col-sm-8">

            <div id="fb-root"></div>
            <script>(function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=635126583203143";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));</script>

            <div class="fb-follow" data-href="https://www.facebook.com/invoiceninja" data-colorscheme="light" data-layout="button" data-show-faces="false"></div>&nbsp;&nbsp;

            <a href="https://twitter.com/invoiceninja" class="twitter-follow-button" data-show-count="false" data-related="hillelcoren" data-size="large">Follow @invoiceninja</a>
            <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
        </div>
    </div>
    -->
    
	{!! Former::actions( 
            Button::success(trans('texts.save'))
                ->submit()->large()
                ->appendIcon(Icon::create('floppy-disk'))) !!}
                                
	{!! Former::close() !!}

@stop
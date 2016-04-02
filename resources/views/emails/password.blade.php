@extends('emails.master_user')

@section('body')
    <div>
        {{ trans('texts.reset_password') }}
    </div>
    &nbsp;
    <div>
        <center>
            @include('partials.email_button', [
                'link' => URL::to("password/reset/{$token}"),
                'field' => 'reset',
                'color' => '#36c157',
            ])
        </center>
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }}<br/>
        {{ trans('texts.email_from') }}
    </div>
    &nbsp;
    <div>
        {{ trans('texts.reset_password_footer') }}
    </div>
@stop
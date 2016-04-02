<tr>
    <td>{{ trans('texts.name') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.balance') }}</td>
    <td>{{ trans('texts.paid_to_date') }}</td>
    <td>{{ trans('texts.address1') }}</td>
    <td>{{ trans('texts.address2') }}</td>
    <td>{{ trans('texts.city') }}</td>
    <td>{{ trans('texts.state') }}</td>
    <td>{{ trans('texts.postal_code') }}</td>
    <td>{{ trans('texts.country') }}</td>
    @if ($account->custom_client_label1)
        <td>{{ $account->custom_client_label1 }}</td>
    @endif
    @if ($account->custom_client_label2)
        <td>{{ $account->custom_client_label2 }}</td>
    @endif
</tr>

@foreach ($clients as $client)
    <tr>
        <td>{{ $client->getDisplayName() }}</td>
        @if ($multiUser)
            <td>{{ $client->user->getDisplayName() }}</td>
        @endif
        <td>{{ $account->formatMoney($client->balance, $client) }}</td>
        <td>{{ $account->formatMoney($client->paid_to_date, $client) }}</td>
        <td>{{ $client->address1 }}</td>
        <td>{{ $client->address2 }}</td>
        <td>{{ $client->city }}</td>
        <td>{{ $client->state }}</td>
        <td>{{ $client->postal_code }}</td>
        <td>{{ $client->present()->country }}</td>
        @if ($account->custom_client_label1)
            <td>{{ $client->custom_value1 }}</td>
        @endif
        @if ($account->custom_client_label2)
            <td>{{ $client->custom_value2 }}</td>
        @endif
    </tr>
@endforeach

<tr><td></td></tr>
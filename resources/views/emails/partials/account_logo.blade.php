@if ($account->hasLogo())
    @if ($account->website)
        <a href="{{ $account->website }}" style="color: #19BB40; text-decoration: underline;">
    @endif

    <img src="{{ $message->embed($account->getLogoFullPath()) }}" style="max-height:50px; max-width:140px; margin-left: 33px;" />

    @if ($account->website)
        </a>
    @endif
@endif

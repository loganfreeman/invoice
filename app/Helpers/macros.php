<?php

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Vendor;

Validator::extend('passcheck', function ($attribute, $value, $parameters) {
    return \Hash::check($value, \Auth::user()->getAuthPassword());
});

/*
 * Some macros and blade extensions
 */

Form::macro('rawLabel', function ($name, $value = null, $options = []) {
    $label = Form::label($name, '%s', $options);

    return sprintf($label, $value);
});

Form::macro('labelWithHelp', function ($name, $value, $options, $help_text) {
    $label = Form::label($name, '%s', $options);

    return sprintf($label, $value)
            .'<a style="margin-left: 4px;font-size: 11px;" href="javascript:showHelp('."'".$help_text."'".');" >'
            .'<i class="ico ico-question "></i>'
            .'</a>';
});

Form::macro('customCheckbox', function ($name, $value, $checked = false, $label = false, $options = []) {

//    $checkbox = Form::checkbox($name, $value = null, $checked, $options);
//    $label    = Form::rawLabel();
//
//    $out = '<div class="checkbox custom-checkbox">
//                                <input type="checkbox" name="send_copy" id="send_copy" value="1">
//                                <label for="send_copy">&nbsp;&nbsp;Send a copy to <b>{{$attendee->event->organiser->email}}</b></label>
//            </div>';
//
//    return $out;
});

Form::macro('styledFile', function ($name, $multiple = false) {
    $out = '<div class="styledFile" id="input-'.$name.'">
        <div class="form-group">
            <span class="col-lg-4 col-sm-4 align-right">
                <span class="btn btn-primary btn-file">
                    Browse&hellip; <input name="'.$name.'" type="file" '.($multiple ? 'multiple' : '').'>
                </span>
            </span>
            <div class="col-lg-8 col-sm-8">
            <input type="text" class="form-control" readonly data-bind="value: '.$name.'">
            </div>
            <span style="display: none;" class="input-group-btn btn-upload-file">
                <span class="btn btn-success ">
                    Upload
                </span>
            </span>
        </div>
    </div>';

    return $out;
});

HTML::macro('sortable_link', function ($title, $active_sort, $sort_by, $sort_order, $url_params = [], $class = '', $extra = '') {

    $sort_order = $sort_order == 'asc' ? 'desc' : 'asc';

    $url_params = http_build_query([
        'sort_by'    => $sort_by,
        'sort_order' => $sort_order,
            ] + $url_params);

    $html = "<a href='?$url_params' class='col-sort $class' $extra>";

    $html .= ($active_sort == $sort_by) ? "<b>$title</b>" : $title;

    $html .= ($sort_order == 'desc') ? '<i class="ico-arrow-down22"></i>' : '<i class="ico-arrow-up22"></i>';

    $html .= '</a>';

    return $html;
});

Blade::directive('money', function ($expression) {
    return "<?php echo number_format($expression, 2); ?>";
});


Form::macro('image_data', function($imagePath) {
    return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($imagePath));
});

Form::macro('nav_link', function($url, $text, $url2 = '', $extra = '') {
    $capitalize = config('former.capitalize_translations');
    $class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2.'/*') ) ? ' class="active"' : '';
    if ($capitalize) {
      $title = ucwords(trans("texts.$text")) . Utils::getProLabel($text);
    } else {
      $title = trans("texts.$text")  . Utils::getProLabel($text);
    }
    return '<li'.$class.'><a href="'.URL::to($url).'" '.$extra.'>'.$title.'</a></li>';
});

Form::macro('resource_link', function($url, $text, array $links = []){
  $capitalize = config('former.capitalize_translations');
  $class = ( Request::is($url) || Request::is($url.'/*') ) ? ' class="active"' : '';
  if ($capitalize) {
    $title = ucwords(trans("texts.$text")) ;
  } else {
    $title = trans("texts.$text") ;
  }
  $str = '<li class="dropdown '.$class.'">
         <a href="'.URL::to($url).'" class="dropdown-toggle">'.trans("texts.$text").'</a>';

  $items = [];

  if(!empty($links)){
    foreach($links as $k => $v) {
      $items[] = '<li><a href="'.URL::to($k).'">'.trans("texts.".$v).'</a></li>';
    }
  }

  if(!empty($items)){
      $str.= '<ul class="dropdown-menu" id="menu1">'.implode($items).'</ul>';
  }

  $str .= '</li>';

  return $str;
});

Form::macro('tab_link', function($url, $text, $active = false) {
    $class = $active ? ' class="active"' : '';
    return '<li'.$class.'><a href="'.URL::to($url).'" data-toggle="tab">'.$text.'</a></li>';
});

Form::macro('menu_link', function($type) {
    $types = str_plural($type);
    $Type = ucfirst($type);
    $Types = ucfirst($types);
    $class = ( Request::is($types) || Request::is('*'.$type.'*')) && !Request::is('*settings*') ? ' active' : '';

    $str = '<li class="dropdown '.$class.'">
           <a href="'.URL::to($types).'" class="dropdown-toggle">'.trans("texts.$types").'</a>';

    $items = [];

    if(Auth::user()->hasPermission('create_all')){
           $items[] = '<li><a href="'.URL::to($types.'/create').'">'.trans("texts.new_$type").'</a></li>';
    }

    if ($type == ENTITY_INVOICE) {
        if(!empty($items))$items[] = '<li class="divider"></li>';
        $items[] = '<li><a href="'.URL::to('recurring_invoices').'">'.trans("texts.recurring_invoices").'</a></li>';
        if(Invoice::canCreate())$items[] = '<li><a href="'.URL::to('recurring_invoices/create').'">'.trans("texts.new_recurring_invoice").'</a></li>';
        if (Auth::user()->isPro()) {
            $items[] = '<li class="divider"></li>';
            $items[] = '<li><a href="'.URL::to('quotes').'">'.trans("texts.quotes").'</a></li>';
            if(Invoice::canCreate())$items[] = '<li><a href="'.URL::to('quotes/create').'">'.trans("texts.new_quote").'</a></li>';
        }
    } else if ($type == ENTITY_CLIENT) {
    } else if ($type == ENTITY_EXPENSE) {
if(!empty($items))$items[] = '<li class="divider"></li>';
        $items[] = '<li><a href="'.URL::to('vendors').'">'.trans("texts.vendors").'</a></li>';
        if(Vendor::canCreate())$items[] = '<li><a href="'.URL::to('vendors/create').'">'.trans("texts.new_vendor").'</a></li>';
}

    if(!empty($items)){
        $str.= '<ul class="dropdown-menu" id="menu1">'.implode($items).'</ul>';
    }

    $str .= '</li>';

    return $str;
});

Form::macro('flatButton', function($label, $color) {
    return '<input type="button" value="' . trans("texts.{$label}") . '" style="background-color:' . $color . ';border:0 none;border-radius:5px;padding:12px 40px;margin:0 6px;cursor:hand;display:inline-block;font-size:14px;color:#fff;text-transform:none;font-weight:bold;"/>';
});

Form::macro('emailViewButton', function($link = '#', $entityType = ENTITY_INVOICE) {
    return view('partials.email_button')
                ->with([
                    'link' => $link,
                    'field' => "view_{$entityType}",
                    'color' => '#0b4d78',
                ])
                ->render();
});

Form::macro('emailPaymentButton', function($link = '#') {
    return view('partials.email_button')
                ->with([
                    'link' => $link,
                    'field' => 'pay_now',
                    'color' => '#36c157',
                ])
                ->render();
});

Form::macro('breadcrumbs', function($status = false) {
    $str = '<ol class="breadcrumb">';

    // Get the breadcrumbs by exploding the current path.
    $basePath = Utils::basePath();
    $parts = explode('?', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
    $path = $parts[0];

    if ($basePath != '/') {
        $path = str_replace($basePath, '', $path);
    }
    $crumbs = explode('/', $path);

    foreach ($crumbs as $key => $val) {
        if (is_numeric($val)) {
            unset($crumbs[$key]);
        }
    }

    $crumbs = array_values($crumbs);
    for ($i=0; $i<count($crumbs); $i++) {
        $crumb = trim($crumbs[$i]);
        if (!$crumb) {
            continue;
        }
        if ($crumb == 'company') {
            return '';
        }
        $name = trans("texts.$crumb");
        if ($i==count($crumbs)-1) {
            $str .= "<li class='active'>$name</li>";
        } else {
            $str .= '<li>'.link_to($crumb, $name).'</li>';
        }
    }

    if ($status) {
        $str .= '&nbsp;&nbsp;&nbsp;&nbsp;' . $status;
    }

    return $str . '</ol>';
});

Validator::extend('positive', function($attribute, $value, $parameters) {
    return Utils::parseFloat($value) >= 0;
});

Validator::extend('has_credit', function($attribute, $value, $parameters) {
    $publicClientId = $parameters[0];
    $amount = $parameters[1];

    $client = \App\Models\Client::scope($publicClientId)->firstOrFail();
    $credit = $client->getTotalCredit();

    return $credit >= $amount;
});

// check that the time log elements don't overlap
Validator::extend('time_log', function($attribute, $value, $parameters) {
    $lastTime = 0;
    $value = json_decode($value);
    array_multisort($value);
    foreach ($value as $timeLog) {
        list($startTime, $endTime) = $timeLog;
        if (!$endTime) {
            continue;
        }
        if ($startTime < $lastTime || $startTime > $endTime) {
            return false;
        }
        if ($endTime < min($startTime, $lastTime)) {
            return false;
        }
        $lastTime = max($lastTime, $endTime);
    }
    return true;
});

Validator::extend('less_than', function($attribute, $value, $parameters) {
    return floatval($value) <= floatval($parameters[0]);
});

Validator::replacer('less_than', function($message, $attribute, $rule, $parameters) {
    return str_replace(':value', $parameters[0], $message);
});

Validator::extend('has_counter', function($attribute, $value, $parameters) {
    return !$value || strstr($value, '{$counter}');
});

Validator::extend('valid_contacts', function($attribute, $value, $parameters) {
    foreach ($value as $contact) {
        $validator = Validator::make($contact, [
                'email' => 'email|required_without:first_name',
                'first_name' => 'required_without:email',
            ]);
        if ($validator->fails()) {
            return false;
        }
    }
    return true;
});

Validator::extend('valid_invoice_items', function($attribute, $value, $parameters) {
    $total = 0;
    foreach ($value as $item) {
        $qty = isset($item['qty']) ? $item['qty'] : 1;
        $cost = isset($item['cost']) ? $item['cost'] : 1;
        $total += $qty * $cost;
    }
    return $total <= MAX_INVOICE_AMOUNT;
});

<?php namespace App\Models;

use Eloquent;
use Utils;
use Session;
use DateTime;
use Event;
use Cache;
use App;
use File;
use App\Events\UserSettingsChanged;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Account extends Eloquent
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter = 'App\Ninja\Presenters\AccountPresenter';
    protected $dates = ['deleted_at'];
    protected $hidden = ['ip'];

    protected $fillable = [
        'name',
        'id_number',
        'vat_number',
        'work_email',
        'website',
        'work_phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'size_id',
        'industry_id',
        'email_footer',
        'timezone_id',
        'date_format_id',
        'datetime_format_id',
        'currency_id',
        'language_id',
        'military_time',
    ];

    public static $basicSettings = [
        ACCOUNT_COMPANY_DETAILS,
        ACCOUNT_USER_DETAILS,
        ACCOUNT_LOCALIZATION,
        ACCOUNT_PAYMENTS,
        ACCOUNT_BANKS,
        ACCOUNT_TAX_RATES,
        ACCOUNT_PRODUCTS,
        ACCOUNT_NOTIFICATIONS,
        ACCOUNT_IMPORT_EXPORT,
    ];

    public static $advancedSettings = [
        ACCOUNT_INVOICE_SETTINGS,
        ACCOUNT_INVOICE_DESIGN,
        ACCOUNT_EMAIL_SETTINGS,
        ACCOUNT_TEMPLATES_AND_REMINDERS,
        ACCOUNT_CLIENT_PORTAL,
        ACCOUNT_CHARTS_AND_REPORTS,
        ACCOUNT_DATA_VISUALIZATIONS,
        ACCOUNT_USER_MANAGEMENT,
        ACCOUNT_API_TOKENS,
    ];

    /*
    protected $casts = [
        'invoice_settings' => 'object',
    ];
    */
    public function account_tokens()
    {
        return $this->hasMany('App\Models\AccountToken');
    }

    public function users()
    {
        return $this->hasMany('App\Models\User');
    }

    public function clients()
    {
        return $this->hasMany('App\Models\Client');
    }

    public function contacts()
    {
        return $this->hasMany('App\Models\Contact');
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    public function account_gateways()
    {
        return $this->hasMany('App\Models\AccountGateway');
    }

    public function bank_accounts()
    {
        return $this->hasMany('App\Models\BankAccount');
    }

    public function tax_rates()
    {
        return $this->hasMany('App\Models\TaxRate');
    }

    public function products()
    {
        return $this->hasMany('App\Models\Product');
    }

    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    public function timezone()
    {
        return $this->belongsTo('App\Models\Timezone');
    }

    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    public function date_format()
    {
        return $this->belongsTo('App\Models\DateFormat');
    }

    public function datetime_format()
    {
        return $this->belongsTo('App\Models\DatetimeFormat');
    }

    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    public function default_tax_rate()
    {
        return $this->belongsTo('App\Models\TaxRate');
    }

    public function expenses()
    {
        return $this->hasMany('App\Models\Expense','account_id','id')->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment','account_id','id')->withTrashed();
    }

    public function setIndustryIdAttribute($value)
    {
        $this->attributes['industry_id'] = $value ?: null;
    }

    public function setCountryIdAttribute($value)
    {
        $this->attributes['country_id'] = $value ?: null;
    }

    public function setSizeIdAttribute($value)
    {
        $this->attributes['size_id'] = $value ?: null;
    }



    public function isGatewayConfigured($gatewayId = 0)
    {
        $this->load('account_gateways');

        if ($gatewayId) {
            return $this->getGatewayConfig($gatewayId) != false;
        } else {
            return count($this->account_gateways) > 0;
        }
    }

    public function isEnglish()
    {
        return !$this->language_id || $this->language_id == DEFAULT_LANGUAGE;
    }

    public function hasInvoicePrefix()
    {
        if ( ! $this->invoice_number_prefix && ! $this->quote_number_prefix) {
            return false;
        }

        return $this->invoice_number_prefix != $this->quote_number_prefix;
    }

    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }

        $this->load('users');
        $user = $this->users()->first();

        return $user->getDisplayName();
    }

    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    public function getMomentDateTimeFormat()
    {
        $format = $this->datetime_format ? $this->datetime_format->format_moment : DEFAULT_DATETIME_MOMENT_FORMAT;

        if ($this->military_time) {
            $format = str_replace('h:mm:ss a', 'H:mm:ss', $format);
        }

        return $format;
    }

    public function getMomentDateFormat()
    {
        $format = $this->getMomentDateTimeFormat();
        $format = str_replace('h:mm:ss a', '', $format);
        $format = str_replace('H:mm:ss', '', $format);

        return trim($format);
    }

    public function getTimezone()
    {
        if ($this->timezone) {
            return $this->timezone->name;
        } else {
            return 'US/Eastern';
        }
    }

    public function getDateTime($date = 'now')
    {
        if ( ! $date) {
            return null;
        } elseif ( ! $date instanceof \DateTime) {
            $date = new \DateTime($date);
        }

        $date->setTimeZone(new \DateTimeZone($this->getTimezone()));

        return $date;
    }

    public function getCustomDateFormat()
    {
        return $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT;
    }

    public function formatMoney($amount, $client = null, $hideSymbol = false)
    {
        if ($client && $client->currency_id) {
            $currencyId = $client->currency_id;
        } elseif ($this->currency_id) {
            $currencyId = $this->currency_id;
        } else {
            $currencyId = DEFAULT_CURRENCY;
        }

        if ($client && $client->country_id) {
            $countryId = $client->country_id;
        } elseif ($this->country_id) {
            $countryId = $this->country_id;
        } else {
            $countryId = false;
        }

        $hideSymbol = $this->show_currency_code || $hideSymbol;

        return Utils::formatMoney($amount, $currencyId, $countryId, $hideSymbol);
    }

    public function getCurrencyId()
    {
        return $this->currency_id ?: DEFAULT_CURRENCY;
    }

    public function formatDate($date)
    {
        $date = $this->getDateTime($date);

        if ( ! $date) {
            return null;
        }

        return $date->format($this->getCustomDateFormat());
    }

    public function formatDateTime($date)
    {
        $date = $this->getDateTime($date);

        if ( ! $date) {
            return null;
        }

        return $date->format($this->getCustomDateTimeFormat());
    }

    public function formatTime($date)
    {
        $date = $this->getDateTime($date);

        if ( ! $date) {
            return null;
        }

        return $date->format($this->getCustomTimeFormat());
    }

    public function getCustomTimeFormat()
    {
        return $this->military_time ? 'H:i' : 'g:i a';
    }

    public function getCustomDateTimeFormat()
    {
        $format = $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT;

        if ($this->military_time) {
            $format = str_replace('g:i a', 'H:i', $format);
        }

        return $format;
    }

    public function getGatewayByType($type = PAYMENT_TYPE_ANY)
    {
        foreach ($this->account_gateways as $gateway) {
            if (!$type || $type == PAYMENT_TYPE_ANY) {
                return $gateway;
            } elseif ($gateway->isPaymentType($type)) {
                return $gateway;
            }
        }
        
        return false;
    }

    public function getGatewayConfig($gatewayId)
    {
        foreach ($this->account_gateways as $gateway) {
            if ($gateway->gateway_id == $gatewayId) {
                return $gateway;
            }
        }

        return false;
    }

    public function hasLogo()
    {
        return file_exists($this->getLogoFullPath());
    }

    public function getLogoPath()
    {
        $fileName = 'logo/' . $this->account_key;

        return file_exists($fileName.'.png') ? $fileName.'.png' : $fileName.'.jpg';
    }

    public function getLogoFullPath()
    {
        $fileName = public_path() . '/logo/' . $this->account_key;

        return file_exists($fileName.'.png') ? $fileName.'.png' : $fileName.'.jpg';
    }

    public function getLogoURL()
    {
        return SITE_URL . '/' . $this->getLogoPath();
    }

    public function getToken($name)
    {
        foreach ($this->account_tokens as $token) {
            if ($token->name === $name) {
                return $token->token;
            }
        }

        return null;
    }

    public function getLogoWidth()
    {
        $path = $this->getLogoFullPath();
        if (!file_exists($path)) {
            return 0;
        }
        list($width, $height) = getimagesize($path);

        return $width;
    }

    public function getLogoHeight()
    {
        $path = $this->getLogoFullPath();
        if (!file_exists($path)) {
            return 0;
        }
        list($width, $height) = getimagesize($path);

        return $height;
    }

    public function createInvoice($entityType = ENTITY_INVOICE, $clientId = null)
    {
        $invoice = Invoice::createNew();

        $invoice->is_recurring = false;
        $invoice->is_quote = false;
        $invoice->invoice_date = Utils::today();
        $invoice->start_date = Utils::today();
        $invoice->invoice_design_id = $this->invoice_design_id;
        $invoice->client_id = $clientId;
        
        if ($entityType === ENTITY_RECURRING_INVOICE) {
            $invoice->invoice_number = microtime(true);
            $invoice->is_recurring = true;
        } else {
            if ($entityType == ENTITY_QUOTE) {
                $invoice->is_quote = true;
            }

            if ($this->hasClientNumberPattern($invoice) && !$clientId) {
                // do nothing, we don't yet know the value
            } else {
                $invoice->invoice_number = $this->getNextInvoiceNumber($invoice);
            }
        }
        
        if (!$clientId) {
            $invoice->client = Client::createNew();
            $invoice->client->public_id = 0;
        }

        return $invoice;
    }

    public function getNumberPrefix($isQuote)
    {
        if ( ! $this->isPro()) {
            return '';
        }

        return ($isQuote ? $this->quote_number_prefix : $this->invoice_number_prefix) ?: '';
    }

    public function hasNumberPattern($isQuote)
    {
        if ( ! $this->isPro()) {
            return false;
        }

        return $isQuote ? ($this->quote_number_pattern ? true : false) : ($this->invoice_number_pattern ? true : false);
    }

    public function hasClientNumberPattern($invoice)
    {
        $pattern = $invoice->is_quote ? $this->quote_number_pattern : $this->invoice_number_pattern;
        
        return strstr($pattern, '$custom');
    }

    public function getNumberPattern($invoice)
    {
        $pattern = $invoice->is_quote ? $this->quote_number_pattern : $this->invoice_number_pattern;

        if (!$pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($this->getCounter($invoice->is_quote), 4, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$userId}')) {
            $search[] = '{$userId}';
            $replace[] = str_pad(($invoice->user->public_id + 1), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];
            $replace[] = str_replace($format, date($format), $matches[1]);
        }

        $pattern = str_replace($search, $replace, $pattern);

        if ($invoice->client_id) {
            $pattern = $this->getClientInvoiceNumber($pattern, $invoice);
        }

        return $pattern;
    }

    private function getClientInvoiceNumber($pattern, $invoice)
    {
        if (!$invoice->client) {
            return $pattern;
        }

        $search = [
            '{$custom1}',
            '{$custom2}',
        ];

        $replace = [
            $invoice->client->custom_value1,
            $invoice->client->custom_value2,
        ];

        return str_replace($search, $replace, $pattern);
    }

    public function getCounter($isQuote)
    {
        return $isQuote && !$this->share_counter ? $this->quote_number_counter : $this->invoice_number_counter;
    }

    public function previewNextInvoiceNumber($entityType = ENTITY_INVOICE)
    {
        $invoice = $this->createInvoice($entityType);
        return $this->getNextInvoiceNumber($invoice);
    }

    public function getNextInvoiceNumber($invoice)
    {
        if ($this->hasNumberPattern($invoice->is_quote)) {
            return $this->getNumberPattern($invoice);
        }

        $counter = $this->getCounter($invoice->is_quote);
        $prefix = $this->getNumberPrefix($invoice->is_quote);
        $counterOffset = 0;

        // confirm the invoice number isn't already taken 
        do {
            $number = $prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
            $check = Invoice::scope(false, $this->id)->whereInvoiceNumber($number)->withTrashed()->first();
            $counter++;
            $counterOffset++;
        } while ($check);

        // update the invoice counter to be caught up
        if ($counterOffset > 1) {
            if ($invoice->is_quote && !$this->share_counter) {
                $this->quote_number_counter += $counterOffset - 1;
            } else {
                $this->invoice_number_counter += $counterOffset - 1;
            }

            $this->save();
        }

        return $number;
    }

    public function incrementCounter($invoice)
    {
        if ($invoice->is_quote && !$this->share_counter) {
            $this->quote_number_counter += 1;
        } else {
            $default = $this->invoice_number_counter;
            $actual = Utils::parseInt($invoice->invoice_number);

            if ( ! $this->isPro() && $default != $actual) {
                $this->invoice_number_counter = $actual + 1;
            } else {
                $this->invoice_number_counter += 1;
            }
        }
        
        $this->save();
    }

    public function loadAllData($updatedAt = null)
    {
        $map = [
            'users' => [],
            'clients' => ['contacts'],
            'invoices' => ['invoice_items', 'user', 'client', 'payments'],
            'products' => [],
            'tax_rates' => [],
            'expenses' => ['client', 'invoice', 'vendor'],
            'payments' => ['invoice'],
        ];

        foreach ($map as $key => $values) {
            $this->load([$key => function($query) use ($values, $updatedAt) {
                $query->withTrashed()->with($values);
                if ($updatedAt) {
                    $query->where('updated_at', '>=', $updatedAt);
                }
            }]);
        }        
    }

    public function loadLocalizationSettings($client = false)
    {
        $this->load('timezone', 'date_format', 'datetime_format', 'language');

        $timezone = $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE;
        Session::put(SESSION_TIMEZONE, $timezone);

        Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);
        Session::put(SESSION_DATE_PICKER_FORMAT, $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);

        $currencyId = ($client && $client->currency_id) ? $client->currency_id : $this->currency_id ?: DEFAULT_CURRENCY; 
        $locale = ($client && $client->language_id) ? $client->language->locale : ($this->language_id ? $this->Language->locale : DEFAULT_LOCALE); 

        Session::put(SESSION_CURRENCY, $currencyId);
        Session::put(SESSION_LOCALE, $locale);

        App::setLocale($locale);

        $format = $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT;
        if ($this->military_time) {
            $format = str_replace('g:i a', 'H:i', $format);
        }
        Session::put(SESSION_DATETIME_FORMAT, $format);
    }

    public function getInvoiceLabels()
    {
        $data = [];
        $custom = (array) json_decode($this->invoice_labels);

        $fields = [
            'invoice',
            'invoice_date',
            'due_date',
            'invoice_number',
            'po_number',
            'discount',
            'taxes',
            'tax',
            'item',
            'description',
            'unit_cost',
            'quantity',
            'line_total',
            'subtotal',
            'paid_to_date',
            'balance_due',
            'partial_due',
            'terms',
            'your_invoice',
            'quote',
            'your_quote',
            'quote_date',
            'quote_number',
            'total',
            'invoice_issued_to',
            'quote_issued_to',
            //'date',
            'rate',
            'hours',
            'balance',
            'from',
            'to',
            'invoice_to',
            'details',
            'invoice_no',
            'valid_until',
        ];

        foreach ($fields as $field) {
            if (isset($custom[$field]) && $custom[$field]) {
                $data[$field] = $custom[$field];
            } else {
                $data[$field] = $this->isEnglish() ? uctrans("texts.$field") : trans("texts.$field");
            }
        }

        foreach (['item', 'quantity', 'unit_cost'] as $field) {
            $data["{$field}_orig"] = $data[$field];
        }

        return $data;
    }

    public function isNinjaAccount()
    {
        return $this->account_key === NINJA_ACCOUNT_KEY;
    }

    public function startTrial()
    {
        if ( ! Utils::isNinja()) {
            return;
        }
        
        $this->pro_plan_trial = date_create()->format('Y-m-d');
        $this->save();
    }

    public function isPro()
    {
        if (!Utils::isNinjaProd()) {
            return true;
        }

        if ($this->isNinjaAccount()) {
            return true;
        }

        $datePaid = $this->pro_plan_paid;
        $trialStart = $this->pro_plan_trial;

        if ($datePaid == NINJA_DATE) {
            return true;
        }

        return Utils::withinPastTwoWeeks($trialStart) || Utils::withinPastYear($datePaid);
    }

    public function isTrial()
    {
        if (!Utils::isNinjaProd()) {
            return false;
        }

        if ($this->pro_plan_paid && $this->pro_plan_paid != '0000-00-00') {
            return false;
        }

        return Utils::withinPastTwoWeeks($this->pro_plan_trial);
    }

    public function isEligibleForTrial()
    {
        return ! $this->pro_plan_trial || $this->pro_plan_trial == '0000-00-00';
    }

    public function getCountTrialDaysLeft()
    {
        $interval = Utils::getInterval($this->pro_plan_trial);
        
        return $interval ? 14 - $interval->d : 0;
    }

    public function getRenewalDate()
    {
        if ($this->pro_plan_paid && $this->pro_plan_paid != '0000-00-00') {
            $date = DateTime::createFromFormat('Y-m-d', $this->pro_plan_paid);
            $date->modify('+1 year');
            $date = max($date, date_create());
        } elseif ($this->isTrial()) {
            $date = date_create();
            $date->modify('+'.$this->getCountTrialDaysLeft().' day');
        } else {
            $date = date_create();
        }

        return $date->format('Y-m-d');
    }

    public function isWhiteLabel()
    {
        if ($this->isNinjaAccount()) {
            return false;
        }

        if (Utils::isNinjaProd()) {
            return self::isPro() && $this->pro_plan_paid != NINJA_DATE;
        } else {
            if ($this->pro_plan_paid == NINJA_DATE) {
                return true;
            }
            
            return Utils::withinPastYear($this->pro_plan_paid);
        }
    }

    public function getLogoSize()
    {
        if (!$this->hasLogo()) {
            return 0;
        }

        $filename = $this->getLogoFullPath();
        return round(File::size($filename) / 1000);
    }

    public function isLogoTooLarge()
    {
        return $this->getLogoSize() > MAX_LOGO_FILE_SIZE;
    }

    public function getSubscription($eventId)
    {
        return Subscription::where('account_id', '=', $this->id)->where('event_id', '=', $eventId)->first();
    }

    public function hideFieldsForViz()
    {
        foreach ($this->clients as $client) {
            $client->setVisible([
                'public_id',
                'name',
                'balance',
                'paid_to_date',
                'invoices',
                'contacts',
            ]);

            foreach ($client->invoices as $invoice) {
                $invoice->setVisible([
                    'public_id',
                    'invoice_number',
                    'amount',
                    'balance',
                    'invoice_status_id',
                    'invoice_items',
                    'created_at',
                    'is_recurring',
                    'is_quote',
                ]);

                foreach ($invoice->invoice_items as $invoiceItem) {
                    $invoiceItem->setVisible([
                        'product_key',
                        'cost',
                        'qty',
                    ]);
                }
            }

            foreach ($client->contacts as $contact) {
                $contact->setVisible([
                    'public_id',
                    'first_name',
                    'last_name',
                    'email', ]);
            }
        }

        return $this;
    }

    public function getDefaultEmailSubject($entityType)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = 'reminder';
        }

        return trans("texts.{$entityType}_subject", ['invoice' => '$invoice', 'account' => '$account']);
    }

    public function getEmailSubject($entityType)
    {
        if ($this->isPro()) {
            $field = "email_subject_{$entityType}";
            $value = $this->$field;

            if ($value) {
                return $value;
            }
        }

        return $this->getDefaultEmailSubject($entityType);
    }

    public function getDefaultEmailTemplate($entityType, $message = false)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = ENTITY_INVOICE;
        }

        $template = "<div>\$client,</div><br>";

        if ($this->isPro() && $this->email_design_id != EMAIL_DESIGN_PLAIN) {
            $template .= "<div>" . trans("texts.{$entityType}_message_button", ['amount' => '$amount']) . "</div><br>" .
                         "<div style=\"text-align: center;\">\$viewButton</div><br>";
        } else {
            $template .= "<div>" . trans("texts.{$entityType}_message", ['amount' => '$amount']) . "</div><br>" .
                         "<div>\$viewLink</div><br>";
        }

        if ($message) {
            $template .= "$message<p/>\r\n\r\n";
        }

        return $template . "\$footer";
    }

    public function getEmailTemplate($entityType, $message = false)
    {
        $template = false;

        if ($this->isPro()) {
            $field = "email_template_{$entityType}";
            $template = $this->$field;
        }
        
        if (!$template) {
            $template = $this->getDefaultEmailTemplate($entityType, $message);
        }

        // <br/> is causing page breaks with the email designs
        return str_replace('/>', ' />', $template);
    }

    public function getEmailFooter()
    {
        if ($this->email_footer) {
            // Add line breaks if HTML isn't already being used
            return strip_tags($this->email_footer) == $this->email_footer ? nl2br($this->email_footer) : $this->email_footer;
        } else {
            return "<p>" . trans('texts.email_signature') . "\n<br>\$account</ p>";
        }
    }

    public function getReminderDate($reminder)
    {
        if ( ! $this->{"enable_reminder{$reminder}"}) {
            return false;
        }

        $numDays = $this->{"num_days_reminder{$reminder}"};
        $plusMinus = $this->{"direction_reminder{$reminder}"} == REMINDER_DIRECTION_AFTER ? '-' : '+';

        return date('Y-m-d', strtotime("$plusMinus $numDays days"));
    }

    public function getInvoiceReminder($invoice)
    {
        for ($i=1; $i<=3; $i++) {
            if ($date = $this->getReminderDate($i)) {
                $field = $this->{"field_reminder{$i}"} == REMINDER_FIELD_DUE_DATE ? 'due_date' : 'invoice_date';
                if ($invoice->$field == $date) {
                    return "reminder{$i}";
                }
            }
        }

        return false;
    }

    public function showTokenCheckbox()
    {
        if (!$this->isGatewayConfigured(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->token_billing_type_id == TOKEN_BILLING_OPT_IN
                || $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    public function selectTokenCheckbox()
    {
        return $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    public function getSiteUrl()
    {
        $url = SITE_URL;
        $iframe_url = $this->iframe_url;
                
        if ($iframe_url) {
            return "{$iframe_url}/?";
        } else if ($this->subdomain) {
            $url = Utils::replaceSubdomain($url, $this->subdomain);
        }

        return $url;
    }

    public function checkSubdomain($host)
    {
        if (!$this->subdomain) {
            return true;
        }

        $server = explode('.', $host);
        $subdomain = $server[0];

        if (!in_array($subdomain, ['app', 'www']) && $subdomain != $this->subdomain) {
            return false;
        }

        return true;
    }

    public function showCustomField($field, $entity = false)
    {
        if ($this->isPro()) {
            return $this->$field ? true : false;
        }

        if (!$entity) {
            return false;
        }
        
        // convert (for example) 'custom_invoice_label1' to 'invoice.custom_value1'
        $field = str_replace(['invoice_', 'label'], ['', 'value'], $field);
        
        return Utils::isEmpty($entity->$field) ? false : true;
    }

    public function attatchPDF()
    {
        return $this->isPro() && $this->pdf_email_attachment;
    }
    
    public function getEmailDesignId()
    {
        return $this->isPro() ? $this->email_design_id : EMAIL_DESIGN_PLAIN;
    }

    public function clientViewCSS(){
        $css = null;
        
        if ($this->isPro()) {
            $bodyFont = $this->getBodyFontCss();
            $headerFont = $this->getHeaderFontCss();
            
            $css = 'body{'.$bodyFont.'}';
            if ($headerFont != $bodyFont) {
                $css .= 'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{'.$headerFont.'}';
            }
            
            if ((Utils::isNinja() && $this->isPro()) || $this->isWhiteLabel()) {
                // For self-hosted users, a white-label license is required for custom CSS
                $css .= $this->client_view_css;
            }
        }
        
        return $css;
    }
    
    public function hasLargeFont()
    {
        foreach (['chinese', 'japanese'] as $language) {
            if (stripos($this->getBodyFontName(), $language) || stripos($this->getHeaderFontName(), $language)) {
                return true;
            }
        }
        
        return false;
    }

    public function getFontsUrl($protocol = ''){
        $bodyFont = $this->getHeaderFontId();
        $headerFont = $this->getBodyFontId();

        $bodyFontSettings = Utils::getFromCache($bodyFont, 'fonts');
        $google_fonts = array($bodyFontSettings['google_font']);
        
        if($headerFont != $bodyFont){
            $headerFontSettings = Utils::getFromCache($headerFont, 'fonts');
            $google_fonts[] = $headerFontSettings['google_font'];
        }

        return ($protocol?$protocol.':':'').'//fonts.googleapis.com/css?family='.implode('|',$google_fonts);
    }
    
    public function getHeaderFontId() {
        return ($this->isPro() && $this->header_font_id) ? $this->header_font_id : DEFAULT_HEADER_FONT;
    }

    public function getBodyFontId() {
        return ($this->isPro() && $this->body_font_id) ? $this->body_font_id : DEFAULT_BODY_FONT;
    }

    public function getHeaderFontName(){
        return Utils::getFromCache($this->getHeaderFontId(), 'fonts')['name'];
    }
    
    public function getBodyFontName(){
        return Utils::getFromCache($this->getBodyFontId(), 'fonts')['name'];
    }
    
    public function getHeaderFontCss($include_weight = true){
        $font_data = Utils::getFromCache($this->getHeaderFontId(), 'fonts');
        $css = 'font-family:'.$font_data['css_stack'].';';
            
        if($include_weight){
            $css .= 'font-weight:'.$font_data['css_weight'].';';
        }
            
        return $css;
    }
    
    public function getBodyFontCss($include_weight = true){
        $font_data = Utils::getFromCache($this->getBodyFontId(), 'fonts');
        $css = 'font-family:'.$font_data['css_stack'].';';
            
        if($include_weight){
            $css .= 'font-weight:'.$font_data['css_weight'].';';
        }
            
        return $css;
    }
    
    public function getFonts(){
        return array_unique(array($this->getHeaderFontId(), $this->getBodyFontId()));
    }
    
    public function getFontsData(){
        $data = array();
        
        foreach($this->getFonts() as $font){
            $data[] = Utils::getFromCache($font, 'fonts');
        }
        
        return $data;
    }
    
    public function getFontFolders(){
        return array_map(function($item){return $item['folder'];}, $this->getFontsData());
    }
}

Account::updated(function ($account) {
    Event::fire(new UserSettingsChanged());
});

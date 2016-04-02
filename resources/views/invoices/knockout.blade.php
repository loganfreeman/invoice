<script type="text/javascript">

function ViewModel(data) {
    var self = this;
    self.showMore = ko.observable(false);

    //self.invoice = data ? false : new InvoiceModel();
    self.invoice = ko.observable(data ? false : new InvoiceModel());
    self.expense_currency_id = ko.observable();
    self.tax_rates = ko.observableArray();
    self.tax_rates.push(new TaxRateModel());  // add blank row
    self.products = {!! $products !!};

    self.loadClient = function(client) {
        ko.mapping.fromJS(client, model.invoice().client().mapping, model.invoice().client);
        @if (!$invoice->id)
            self.setDueDate();
        @endif
    }

    self.showMoreFields = function() {
        self.showMore(!self.showMore());
    }

    self.setDueDate = function() {
        @if ($entityType == ENTITY_INVOICE)
            var paymentTerms = parseInt(self.invoice().client().payment_terms());
            if (paymentTerms && paymentTerms != 0 && !self.invoice().due_date())
            {
                if (paymentTerms == -1) paymentTerms = 0;
                var dueDate = $('#invoice_date').datepicker('getDate');
                dueDate.setDate(dueDate.getDate() + paymentTerms);
                self.invoice().due_date(dueDate);
                // We're using the datepicker to handle the date formatting
                self.invoice().due_date($('#due_date').val());
            }
        @endif
    }

    self.invoice_taxes = ko.observable({{ Auth::user()->account->invoice_taxes ? 'true' : 'false' }});
    self.invoice_item_taxes = ko.observable({{ Auth::user()->account->invoice_item_taxes ? 'true' : 'false' }});
    self.show_item_taxes = ko.observable({{ Auth::user()->account->show_item_taxes ? 'true' : 'false' }});

    self.mapping = {
        'invoice': {
            create: function(options) {
                return new InvoiceModel(options.data);
            }
        },
        'tax_rates': {
            create: function(options) {
                return new TaxRateModel(options.data);
            }
        },
    }

    if (data) {
        ko.mapping.fromJS(data, self.mapping, self);
    }

    self.invoice_taxes.show = ko.computed(function() {
        if (self.invoice_taxes() && self.tax_rates().length > 1) {
            return true;
        }
        if (self.invoice().tax_rate() > 0) {
            return true;
        }
        return false;
    });

    self.invoice_item_taxes.show = ko.computed(function() {
        if (self.invoice_item_taxes()) {
            return true;
        }
        for (var i=0; i<self.invoice().invoice_items().length; i++) {
            var item = self.invoice().invoice_items()[i];
            if (item.tax_rate() > 0) {
                return true;
            }
        }
        return false;
    });

    self.addTaxRate = function(data) {
        var itemModel = new TaxRateModel(data);
        self.tax_rates.push(itemModel);
        applyComboboxListeners();
    }

    self.getTaxRateById = function(id) {
        for (var i=0; i<self.tax_rates().length; i++) {
            var taxRate = self.tax_rates()[i];
            if (taxRate.public_id() == id) {
                return taxRate;
            }
        }
        return false;
    }

    self.getTaxRate = function(name, rate) {
        for (var i=0; i<self.tax_rates().length; i++) {
            var taxRate = self.tax_rates()[i];
            if (taxRate.name() == name && taxRate.rate() == parseFloat(rate)) {
                return taxRate;
            }
        }

        var taxRate = new TaxRateModel();
        taxRate.name(name);
        taxRate.rate(parseFloat(rate));
        if (name) {
            taxRate.is_deleted(true);
            self.tax_rates.push(taxRate);
        }
        return taxRate;
    }

    self.showClientForm = function() {
        trackEvent('/activity', '/view_client_form');
        self.clientBackup = ko.mapping.toJS(self.invoice().client);

        $('#emailError').css( "display", "none" );
        $('#clientModal').modal('show');
    }

    self.clientFormComplete = function() {
        trackEvent('/activity', '/save_client_form');

        var email = $("[name='client[contacts][0][email]']").val();
        var firstName = $("[name='client[contacts][0][first_name]']").val();
        var lastName = $("[name='client[contacts][0][last_name]']").val();
        var name = $("[name='client[name]']").val();

        if (name) {
            //
        } else if (firstName || lastName) {
            name = firstName + ' ' + lastName;
        } else {
            name = email;
        }

        var isValid = true;
        $('input.client-email').each(function(item, value) {
            var email = $(value).val();
            if (!firstName && (!email || !isValidEmailAddress(email))) {
                isValid = false;
            }
        });

        if (!isValid) {
            $('#emailError').css( "display", "inline" );
            return;
        }

        if (self.invoice().client().public_id() == 0) {
            self.invoice().client().public_id(-1);
            self.invoice().client().invoice_number_counter = 1;
            self.invoice().client().quote_number_counter = 1;
        }

        model.setDueDate();
        setComboboxValue($('.client_select'), -1, name);

        var client = $.parseJSON(ko.toJSON(self.invoice().client()));
        setInvoiceNumber(client);

        //$('.client_select select').combobox('setSelected');
        //$('.client_select input.form-control').val(name);
        //$('.client_select .combobox-container').addClass('combobox-selected');

        $('#emailError').css( "display", "none" );

        refreshPDF(true);
        model.clientBackup = false;
        $('#clientModal').modal('hide');
    }

    self.clientLinkText = ko.computed(function() {
        if (self.invoice().client().public_id())
        {
            return "{{ trans('texts.edit_client') }}";
        }
        else
        {
            if (clients.length > {{ Auth::user()->getMaxNumClients() }})
            {
                return '';
            }
            else
            {
                return "{{ trans('texts.create_new_client') }}";
            }
        }
    });
}

function InvoiceModel(data) {
    var self = this;
    this.client = ko.observable(data ? false : new ClientModel());
    self.account = {!! $account !!};
    self.id = ko.observable('');
    self.discount = ko.observable('');
    self.is_amount_discount = ko.observable(0);
    self.frequency_id = ko.observable(4); // default to monthly
    self.terms = ko.observable('');
    self.default_terms = ko.observable(account.{{ $entityType }}_terms);
    self.terms_placeholder = ko.observable({{ !$invoice->id && $account->{"{$entityType}_terms"} ? "account.{$entityType}_terms" : false}});
    self.set_default_terms = ko.observable(false);
    self.invoice_footer = ko.observable('');
    self.default_footer = ko.observable(account.invoice_footer);
    self.footer_placeholder = ko.observable({{ !$invoice->id && $account->invoice_footer ? 'account.invoice_footer' : false}});
    self.set_default_footer = ko.observable(false);
    self.public_notes = ko.observable('');
    self.po_number = ko.observable('');
    self.invoice_date = ko.observable('');
    self.invoice_number = ko.observable('');
    self.due_date = ko.observable('');
    self.recurring_due_date = ko.observable('');
    self.start_date = ko.observable('');
    self.end_date = ko.observable('');
    self.last_sent_date = ko.observable('');
    self.tax_name = ko.observable();
    self.tax_rate = ko.observable();
    self.is_recurring = ko.observable(0);
    self.is_quote = ko.observable({{ $entityType == ENTITY_QUOTE ? '1' : '0' }});
    self.auto_bill = ko.observable();
    self.invoice_status_id = ko.observable(0);
    self.invoice_items = ko.observableArray();
    self.amount = ko.observable(0);
    self.balance = ko.observable(0);
    self.invoice_design_id = ko.observable(1);
    self.partial = ko.observable(0);
    self.has_tasks = ko.observable();
    self.has_expenses = ko.observable();

    self.custom_value1 = ko.observable(0);
    self.custom_value2 = ko.observable(0);
    self.custom_taxes1 = ko.observable(false);
    self.custom_taxes2 = ko.observable(false);
    self.custom_text_value1 = ko.observable();
    self.custom_text_value2 = ko.observable();

    self.mapping = {
        'client': {
            create: function(options) {
                return new ClientModel(options.data);
            }
        },
        'invoice_items': {
            create: function(options) {
                return new ItemModel(options.data);
            }
        },
        'tax': {
            create: function(options) {
                return new TaxRateModel(options.data);
            }
        },
    }

    self.addItem = function() {
        var itemModel = new ItemModel();
        @if ($account->hide_quantity)
            itemModel.qty(1);
        @endif
        self.invoice_items.push(itemModel);
        applyComboboxListeners();
        return itemModel;
    }

    if (data) {
        ko.mapping.fromJS(data, self.mapping, self);
    } else {
        self.addItem();
    }

    self.qtyLabel = ko.computed(function() {
        return self.has_tasks() ? invoiceLabels['hours'] : invoiceLabels['quantity'];
    }, this);

    self.costLabel = ko.computed(function() {
        return self.has_tasks() ? invoiceLabels['rate'] : invoiceLabels['unit_cost'];
    }, this);

    self._tax = ko.observable();
    this.tax = ko.computed({
        read: function () {
            return self._tax();
        },
        write: function(value) {
            if (value) {
                self._tax(value);
                self.tax_name(value.name());
                self.tax_rate(value.rate());
            } else {
                self._tax(false);
                self.tax_name('');
                self.tax_rate(0);
            }
        }
    })

    self.wrapped_terms = ko.computed({
        read: function() {
            return this.terms();
        },
        write: function(value) {
            value = wordWrapText(value, 300);
            self.terms(value);
        },
        owner: this
    });


    self.wrapped_notes = ko.computed({
        read: function() {
            return this.public_notes();
        },
        write: function(value) {
            value = wordWrapText(value, 300);
            self.public_notes(value);
        },
        owner: this
    });

    self.wrapped_footer = ko.computed({
        read: function() {
            return this.invoice_footer();
        },
        write: function(value) {
            value = wordWrapText(value, 600);
            self.invoice_footer(value);
        },
        owner: this
    });

    self.removeItem = function(item) {
        self.invoice_items.remove(item);
        refreshPDF(true);
    }

    self.formatMoney = function(amount) {
        var client = $.parseJSON(ko.toJSON(self.client()));
        return formatMoneyAccount(amount, self.account, client);
    }

    self.totals = ko.observable();

    self.totals.rawSubtotal = ko.computed(function() {
        var total = 0;
        for(var p=0; p < self.invoice_items().length; ++p) {
            var item = self.invoice_items()[p];
            total += item.totals.rawTotal();
        }
        return total;
    });

    self.totals.subtotal = ko.computed(function() {
        var total = self.totals.rawSubtotal();
        return self.formatMoney(total);
    });

    self.totals.rawDiscounted = ko.computed(function() {
        if (parseInt(self.is_amount_discount())) {
            return roundToTwo(self.discount());
        } else {
            return roundToTwo(self.totals.rawSubtotal() * (self.discount()/100));
        }
    });

    self.totals.discounted = ko.computed(function() {
        return self.formatMoney(self.totals.rawDiscounted());
    });

    self.totals.taxAmount = ko.computed(function() {
        var total = self.totals.rawSubtotal();
        var discount = self.totals.rawDiscounted();
        total -= discount;

        var customValue1 = roundToTwo(self.custom_value1());
        var customValue2 = roundToTwo(self.custom_value2());
        var customTaxes1 = self.custom_taxes1() == 1;
        var customTaxes2 = self.custom_taxes2() == 1;

        if (customValue1 && customTaxes1) {
            total = NINJA.parseFloat(total) + customValue1;
        }
        if (customValue2 && customTaxes2) {
            total = NINJA.parseFloat(total) + customValue2;
        }

        var taxRate = parseFloat(self.tax_rate());
        //if (taxRate > 0) {
        //    var tax = roundToTwo(total * (taxRate/100));
        //    return self.formatMoney(tax);
        //} else {
        //    return self.formatMoney(0);
        //}
        var tax = roundToTwo(total * (taxRate/100));
        return self.formatMoney(tax);
    });

    self.totals.itemTaxes = ko.computed(function() {
        var taxes = {};
        var total = self.totals.rawSubtotal();
        for(var i=0; i<self.invoice_items().length; i++) {
            var item = self.invoice_items()[i];
            var lineTotal = item.totals.rawTotal();
            if (self.discount()) {
                if (parseInt(self.is_amount_discount())) {
                    lineTotal -= roundToTwo((lineTotal/total) * self.discount());
                } else {
                    lineTotal -= roundToTwo(lineTotal * (self.discount()/100));
                }
            }
            var taxAmount = roundToTwo(lineTotal * item.tax_rate() / 100);
            if (taxAmount) {
                var key = item.tax_name() + item.tax_rate();
                if (taxes.hasOwnProperty(key)) {
                    taxes[key].amount += taxAmount;
                } else {
                    taxes[key] = {name:item.tax_name(), rate:item.tax_rate(), amount:taxAmount};
                }
            }
        }
        return taxes;
    });

    self.totals.hasItemTaxes = ko.computed(function() {
        var count = 0;
        var taxes = self.totals.itemTaxes();
        for (var key in taxes) {
            if (taxes.hasOwnProperty(key)) {
                count++;
            }
        }
        return count > 0;
    });

    self.totals.itemTaxRates = ko.computed(function() {
        var taxes = self.totals.itemTaxes();
        var parts = [];
        for (var key in taxes) {
            if (taxes.hasOwnProperty(key)) {
                parts.push(taxes[key].name + ' ' + (taxes[key].rate*1) + '%');
            }
        }
        return parts.join('<br/>');
    });

    self.totals.itemTaxAmounts = ko.computed(function() {
        var taxes = self.totals.itemTaxes();
        var parts = [];
        for (var key in taxes) {
            if (taxes.hasOwnProperty(key)) {
                parts.push(self.formatMoney(taxes[key].amount));
            }
        }
        return parts.join('<br/>');
    });

    self.totals.rawPaidToDate = ko.computed(function() {
        return accounting.toFixed(self.amount(),2) - accounting.toFixed(self.balance(),2);
    });

    self.totals.paidToDate = ko.computed(function() {
        var total = self.totals.rawPaidToDate();
        return self.formatMoney(total);
    });

    self.totals.rawTotal = ko.computed(function() {
        var total = accounting.toFixed(self.totals.rawSubtotal(),2);
        var discount = self.totals.rawDiscounted();
        total -= discount;

        var customValue1 = roundToTwo(self.custom_value1());
        var customValue2 = roundToTwo(self.custom_value2());
        var customTaxes1 = self.custom_taxes1() == 1;
        var customTaxes2 = self.custom_taxes2() == 1;

        if (customValue1 && customTaxes1) {
            total = NINJA.parseFloat(total) + customValue1;
        }
        if (customValue2 && customTaxes2) {
            total = NINJA.parseFloat(total) + customValue2;
        }

        var taxRate = parseFloat(self.tax_rate());
        total = NINJA.parseFloat(total) + roundToTwo(total * (taxRate/100));
        total = roundToTwo(total);

        var taxes = self.totals.itemTaxes();
        for (var key in taxes) {
            if (taxes.hasOwnProperty(key)) {
                total += taxes[key].amount;
            }
        }

        if (customValue1 && !customTaxes1) {
            total = NINJA.parseFloat(total) + customValue1;
        }
        if (customValue2 && !customTaxes2) {
            total = NINJA.parseFloat(total) + customValue2;
        }

        var paid = self.totals.rawPaidToDate();
        if (paid > 0) {
            total -= paid;
        }

        return total;
    });

    self.totals.total = ko.computed(function() {
        return self.formatMoney(self.totals.rawTotal());
    });

    self.totals.partial = ko.computed(function() {
        return self.formatMoney(self.partial());
    });

    self.onDragged = function(item) {
        refreshPDF(true);
    }

    self.showResetTerms = function() {
        return self.default_terms() && self.terms() != self.default_terms();
    }

    self.showResetFooter = function() {
        return self.default_footer() && self.invoice_footer() != self.default_footer();
    }
}

function ClientModel(data) {
    var self = this;
    self.public_id = ko.observable(0);
    self.name = ko.observable('');
    self.id_number = ko.observable('');
    self.vat_number = ko.observable('');
    self.work_phone = ko.observable('');
    self.custom_value1 = ko.observable('');
    self.custom_value2 = ko.observable('');
    self.private_notes = ko.observable('');
    self.address1 = ko.observable('');
    self.address2 = ko.observable('');
    self.city = ko.observable('');
    self.state = ko.observable('');
    self.postal_code = ko.observable('');
    self.country_id = ko.observable('');
    self.size_id = ko.observable('');
    self.industry_id = ko.observable('');
    self.currency_id = ko.observable('');
    self.language_id = ko.observable('');
    self.website = ko.observable('');
    self.payment_terms = ko.observable(0);
    self.contacts = ko.observableArray();

    self.mapping = {
        'contacts': {
            create: function(options) {
                var model = new ContactModel(options.data);
                model.send_invoice(options.data.send_invoice == '1');
                return model;
            }
        }
    }

    self.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
    self.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }

    self.addContact = function() {
        var contact = new ContactModel();
        contact.send_invoice(true);
        self.contacts.push(contact);
        return false;
    }

    self.removeContact = function() {
        self.contacts.remove(this);
    }

    self.name.display = ko.computed(function() {
        if (self.name()) {
            return self.name();
        }
        if (self.contacts().length == 0) return;
        var contact = self.contacts()[0];
        if (contact.first_name() || contact.last_name()) {
            return contact.first_name() + ' ' + contact.last_name();
        } else {
            return contact.email();
        }
    });

    self.name.placeholder = ko.computed(function() {
        if (self.contacts().length == 0) return '';
        var contact = self.contacts()[0];
        if (contact.first_name() || contact.last_name()) {
            return contact.first_name() + ' ' + contact.last_name();
        } else {
            return contact.email();
        }
    });

    if (data) {
        ko.mapping.fromJS(data, {}, this);
    } else {
        self.addContact();
    }
}

function ContactModel(data) {
    var self = this;
    self.public_id = ko.observable('');
    self.first_name = ko.observable('');
    self.last_name = ko.observable('');
    self.email = ko.observable('');
    self.phone = ko.observable('');
    self.send_invoice = ko.observable(false);
    self.invitation_link = ko.observable('');
    self.invitation_status = ko.observable('');
    self.invitation_viewed = ko.observable(false);
    self.email_error = ko.observable('');

    if (data) {
        ko.mapping.fromJS(data, {}, this);
    }

    self.displayName = ko.computed(function() {
        var str = '';
        if (self.first_name() || self.last_name()) {
            str += (self.first_name() || '') + ' ' + (self.last_name() || '') + '\n';
        }
        if (self.email()) {
            str += self.email() + '\n';
        }

        return str;
    });

    self.email.display = ko.computed(function() {
        var str = '';

        if (self.first_name() || self.last_name()) {
            str += (self.first_name() || '') + ' ' + (self.last_name() || '') + '<br/>';
        }
        if (self.email()) {
            str += self.email() + '<br/>';
        }
        return str;
    });

    self.view_as_recipient = ko.computed(function() {
        var str = '';
        @if (Utils::isConfirmed())
        if (self.invitation_link()) {
            str += '<a href="' + self.invitation_link() + '" target="_blank">{{ trans('texts.view_as_recipient') }}</a>';
        }
        @endif

        return str;
    });
}

function TaxRateModel(data) {
    var self = this;
    self.public_id = ko.observable('');
    self.rate = ko.observable(0);
    self.name = ko.observable('');
    self.is_deleted = ko.observable(false);
    self.is_blank = ko.observable(false);
    self.actionsVisible = ko.observable(false);

    if (data) {
        ko.mapping.fromJS(data, {}, this);
    }

    this.prettyRate = ko.computed({
        read: function () {
            return this.rate() ? roundToTwo(this.rate()) : '';
        },
        write: function (value) {
            this.rate(value);
        },
        owner: this
    });


    self.displayName = ko.computed({
        read: function () {
            var name = self.name() ? self.name() : '';
            var rate = self.rate() ? parseFloat(self.rate()) + '%' : '';
            return name + ' ' + rate;
        },
        write: function (value) {
            // do nothing
        },
        owner: this
    });

    self.hideActions = function() {
        self.actionsVisible(false);
    }

    self.showActions = function() {
        self.actionsVisible(true);
    }

    self.isEmpty = function() {
        return !self.rate() && !self.name();
    }
}

function ItemModel(data) {
    var self = this;
    self.product_key = ko.observable('');
    self.notes = ko.observable('');
    self.cost = ko.observable(0);
    self.qty = ko.observable(0);
    self.custom_value1 = ko.observable('');
    self.custom_value2 = ko.observable('');
    self.tax_name = ko.observable('');
    self.tax_rate = ko.observable(0);
    self.task_public_id = ko.observable('');
    self.expense_public_id = ko.observable('');
    self.actionsVisible = ko.observable(false);

    self._tax = ko.observable();
    this.tax = ko.computed({
        read: function () {
            return self._tax();
        },
        write: function(value) {
            self._tax(value);
            self.tax_name(value.name());
            self.tax_rate(value.rate());
        }
    })

    this.prettyQty = ko.computed({
        read: function () {
            return NINJA.parseFloat(this.qty()) ? NINJA.parseFloat(this.qty()) : '';
        },
        write: function (value) {
            this.qty(value);
        },
        owner: this
    });

    this.prettyCost = ko.computed({
        read: function () {
            return this.cost() ? this.cost() : '';
        },
        write: function (value) {
            this.cost(value);
        },
        owner: this
    });

    self.mapping = {
        'tax': {
            create: function(options) {
                return new TaxRateModel(options.data);
            }
        }
    }

    if (data) {
        ko.mapping.fromJS(data, self.mapping, this);
    }

    self.wrapped_notes = ko.computed({
        read: function() {
            return this.notes();
        },
        write: function(value) {
            value = wordWrapText(value, 235);
            self.notes(value);
            onItemChange();
        },
        owner: this
    });

    this.totals = ko.observable();

    this.totals.rawTotal = ko.computed(function() {
        var cost = roundToTwo(NINJA.parseFloat(self.cost()));
        var qty = roundToTwo(NINJA.parseFloat(self.qty()));
        var value = cost * qty;
        return value ? roundToTwo(value) : 0;
    });

    this.totals.total = ko.computed(function() {
        var total = self.totals.rawTotal();
        return window.hasOwnProperty('model') && total ? model.invoice().formatMoney(total) : '';
    });

    this.hideActions = function() {
        this.actionsVisible(false);
    }

    this.showActions = function() {
        this.actionsVisible(true);
    }

    this.isEmpty = function() {
        return !self.product_key() && !self.notes() && !self.cost() && (!self.qty() || {{ $account->hide_quantity ? 'true' : 'false' }});
    }

    this.onSelect = function() {}
}

/* Custom binding for product key typeahead */
ko.bindingHandlers.typeahead = {
    init: function (element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
        var $element = $(element);
        var allBindings = allBindingsAccessor();
        
        $element.typeahead({
            highlight: true,
            minLength: 0,
        },
        {
            name: 'data',
            display: allBindings.key,
            source: searchData(allBindings.items, allBindings.key)
        }).on('typeahead:select', function(element, datum, name) {
            @if (Auth::user()->account->fill_products)
                var model = ko.dataFor(this);
                if (model.expense_public_id()) {
                    return;
                }
                if (datum.notes) {
                    model.notes(datum.notes);
                }
                if (datum.cost) {
                    model.cost(accounting.toFixed(datum.cost, 2));
                }
                if (!model.qty()) {
                    model.qty(1);
                }
                @if ($account->invoice_item_taxes)
                    if (datum.default_tax_rate) {
                        model.tax(self.model.getTaxRateById(datum.default_tax_rate.public_id));
                    }
                @endif
            @endif
            onItemChange();
        }).on('typeahead:change', function(element, datum, name) {
            var value = valueAccessor();
            value(datum);
            onItemChange();
            refreshPDF(true);
        });
    },

    update: function (element, valueAccessor) {
        var value = ko.utils.unwrapObservable(valueAccessor());
        if (value) {
            $(element).typeahead('val', value);
        }
    }
};

</script>
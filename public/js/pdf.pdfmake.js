var NINJA = NINJA || {};

NINJA.TEMPLATES = {
    CLEAN: "1",
    BOLD:"2",
    MODERN: "3",
    NORMAL:"4",
    BUSINESS:"5",
    CREATIVE:"6",
    ELEGANT:"7",
    HIPSTER:"8",
    PLAYFUL:"9",
    PHOTO:"10"
};

function GetPdfMake(invoice, javascript, callback) {    

    javascript = NINJA.decodeJavascript(invoice, javascript);

    function jsonCallBack(key, val) {

        // handle custom functions
        if (typeof val === 'string') {
            if (val.indexOf('$firstAndLast') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return (i === 0 || i === node.table.body.length) ? parseFloat(parts[1]) : 0;
                };
            } else if (val.indexOf('$none') === 0) {
                return function (i, node) {
                    return 0;
                };
            } else if (val.indexOf('$notFirstAndLastColumn') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return (i === 0 || i === node.table.widths.length) ? 0 : parseFloat(parts[1]);
                };
            } else if (val.indexOf('$notFirst') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return i === 0 ? 0 : parseFloat(parts[1]);
                };
            } else if (val.indexOf('$amount') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return parseFloat(parts[1]);
                };
            } else if (val.indexOf('$primaryColor') === 0) {
                var parts = val.split(':');
                return NINJA.primaryColor || parts[1];
            } else if (val.indexOf('$secondaryColor') === 0) {
                var parts = val.split(':');
                return NINJA.secondaryColor || parts[1];
            }
        }

        // determine whether or not to show the header/footer
        if (invoice.is_pro) {
            if (key === 'header') {
                return function(page, pages) {
                    return page === 1 || invoice.account.all_pages_header == '1' ? val : '';
                }
            } else if (key === 'footer') {
                return function(page, pages) {
                    return page === pages || invoice.account.all_pages_footer == '1' ? val : '';
                }
            }
        }

        // check for markdown
        if (key === 'text') {
            val = NINJA.parseMarkdownText(val, true);
        }

        /*
        if (key === 'stack') {
            val = NINJA.parseMarkdownStack(val);
            val = NINJA.parseMarkdownText(val, false);
        }
        */
        
        return val;
    }


    // Add ninja logo to the footer
    var dd = JSON.parse(javascript, jsonCallBack);
    var designId = invoice.invoice_design_id;
    if (!invoice.is_pro) {
        if (designId == NINJA.TEMPLATES.CLEAN || designId == NINJA.TEMPLATES.NORMAL) {
            dd.footer.columns.push({image: logoImages.imageLogo1, alignment: 'right', width: 130, margin: [0, 0, 0, 0]})
        } else if (designId == NINJA.TEMPLATES.BOLD) {
            dd.footer[1].columns.push({image: logoImages.imageLogo2, alignment: 'right', width: 130, margin: [0, -20, 20, 0]})
        } else if (designId == NINJA.TEMPLATES.MODERN) {
            dd.footer[1].columns[0].stack.push({image: logoImages.imageLogo3, alignment: 'left', width: 130, margin: [40, 6, 0, 0]});
        }
    }
    
    
    
    pdfMake.fonts = {}
    fonts = window.invoiceFonts || invoice.invoice_fonts;

    // Add only the loaded fonts
    $.each(fonts, function(i,font){
        addFont(font);
    });


    function addFont(font){
        if(window.ninjaFontVfs[font.folder]){
            pdfMake.fonts[font.name] = {
                normal: font.folder+'/'+font.normal,
                italics: font.folder+'/'+font.italics,
                bold: font.folder+'/'+font.bold,
                bolditalics: font.folder+'/'+font.bolditalics
            }
        }
    }
        
    if(!dd.defaultStyle)dd.defaultStyle = {font:NINJA.bodyFont};
    else if(!dd.defaultStyle.font)dd.defaultStyle.font = NINJA.bodyFont;
    
    doc = pdfMake.createPdf(dd);
    doc.save = function(fileName) {
        this.download(fileName);
    };
    
    return doc;
}

NINJA.decodeJavascript = function(invoice, javascript)
{
    var account = invoice.account;
    var blankImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVQYV2NgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=';

    // search/replace variables
    var json = {
        'accountName': account.name || ' ',
        'accountLogo': window.accountLogo || blankImage,
        'accountDetails': NINJA.accountDetails(invoice), 
        'accountAddress': NINJA.accountAddress(invoice),
        'invoiceDetails': NINJA.invoiceDetails(invoice),
        'invoiceDetailsHeight': (NINJA.invoiceDetails(invoice).length * 16) + 16,
        'invoiceLineItems': NINJA.invoiceLines(invoice),
        'invoiceLineItemColumns': NINJA.invoiceColumns(invoice),
        'quantityWidth': NINJA.quantityWidth(invoice),
        'taxWidth': NINJA.taxWidth(invoice),
        'clientDetails': NINJA.clientDetails(invoice),
        'notesAndTerms': NINJA.notesAndTerms(invoice),
        'subtotals': NINJA.subtotals(invoice),
        'subtotalsHeight': (NINJA.subtotals(invoice).length * 16) + 16,
        'subtotalsWithoutBalance': NINJA.subtotals(invoice, true),        
        'subtotalsBalance': NINJA.subtotalsBalance(invoice),
        'balanceDue': formatMoneyInvoice(invoice.balance_amount, invoice),
        'invoiceFooter': NINJA.invoiceFooter(invoice),
        'invoiceNumber': invoice.invoice_number || ' ',
        'entityType': invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice,
        'entityTypeUC': (invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice).toUpperCase(),
        'fontSize': NINJA.fontSize,
        'fontSizeLarger': NINJA.fontSize + 1,
        'fontSizeLargest': NINJA.fontSize + 2,
        'fontSizeSmaller': NINJA.fontSize - 1,
        'bodyFont': NINJA.bodyFont,
        'headerFont': NINJA.headerFont,
    }

    for (var key in json) {
        // remove trailing commas for these fields
        if (['quantityWidth', 'taxWidth'].indexOf(key) >= 0) {
            var regExp = new RegExp('"\\$'+key+'",', 'g');
            val = json[key];
        } else {
            var regExp = new RegExp('"\\$'+key+'"', 'g');
            var val = JSON.stringify(json[key]);
            val = doubleDollarSign(val);
        }
        javascript = javascript.replace(regExp, val);
    }

    // search/replace labels 
    var regExp = new RegExp('"\\$\\\w*?Label(UC)?(:)?(\\\?)?"', 'g');
    var matches = javascript.match(regExp);    
    
    if (matches) {
        for (var i=0; i<matches.length; i++) {
            var match = matches[i];
            field = match.substring(2, match.indexOf('Label'));
            field = toSnakeCase(field);
            var value = getDescendantProp(invoice, field);
            if (match.indexOf('?') < 0 || value) {
                if (invoice.partial && field == 'balance_due') {
                    field = 'partial_due';
                } else if (invoice.is_quote) {
                    field = field.replace('invoice', 'quote');
                }
                var label = invoiceLabels[field];
                if (match.indexOf('UC') >= 0) {
                    label = label.toUpperCase();
                }
                if (match.indexOf(':') >= 0) {
                    label = label + ':';
                }
            } else {
                label = ' ';
            }
            javascript = javascript.replace(match, '"'+label+'"');
        }        
    }

    // search/replace values 
    var regExp = new RegExp('"\\$[a-z][\\\w\\\.]*?[Value]?"', 'g');
    var matches = javascript.match(regExp);    

    if (matches) {
        for (var i=0; i<matches.length; i++) {
            var match = matches[i];

            // reserved words
            if (['"$none"', '"$firstAndLast"', '"$notFirstAndLastColumn"', '"$notFirst"', '"$amount"', '"$primaryColor"', '"$secondaryColor"'].indexOf(match) >= 0) {
                continue;
            }

            // legacy style had 'Value' at the end
            if (endsWith(match, 'Value"')) {
                field = match.substring(2, match.indexOf('Value'));
            } else {
                field = match.substring(2, match.length - 1);
            }            
            field = toSnakeCase(field);

            var value = getDescendantProp(invoice, field) || ' ';
            value = doubleDollarSign(value);
            javascript = javascript.replace(match, '"'+value+'"');
        }
    }

    return javascript;
}


NINJA.notesAndTerms = function(invoice)
{
    var data = [];

    if (invoice.public_notes) {
        data.push({stack:[{text: invoice.public_notes, style: ['notes']}]});
        data.push({text:' '});
    }

    if (invoice.terms) {
        data.push({text:invoiceLabels.terms, style: ['termsLabel']});
        data.push({stack:[{text: invoice.terms, style: ['terms']}]});
    }

    return NINJA.prepareDataList(data, 'notesAndTerms');
}

NINJA.invoiceColumns = function(invoice)
{
    var account = invoice.account;
    var columns = [];

    if (invoice.has_product_key) {
        columns.push("15%");
    }

    columns.push("*")

    if (invoice.is_pro && account.custom_invoice_item_label1) {
        columns.push("10%");
    }
    if (invoice.is_pro && account.custom_invoice_item_label2) {
        columns.push("10%");
    }

    var count = 3;
    if (account.hide_quantity == '1') {
        count--;
    }
    if (account.show_item_taxes == '1') {
        count++;
    }
    for (var i=0; i<count; i++) {
        columns.push("14%");
    }

    return columns;
}

NINJA.invoiceFooter = function(invoice)
{
    if (!invoice.is_pro && invoice.invoice_design_id == 3) {
        return invoice.invoice_footer ? invoice.invoice_footer.substring(0, 200) : ' ';
    } else {
        return invoice.invoice_footer || ' ';
    }
}

NINJA.quantityWidth = function(invoice)
{
    return invoice.account.hide_quantity == '1' ? '' : '"14%", ';
}

NINJA.taxWidth = function(invoice)
{
    return invoice.account.show_item_taxes == '1' ? '"14%", ' : '';
}

NINJA.invoiceLines = function(invoice) {
    var account = invoice.account;
    var total = 0;
    var shownItem = false;
    var hideQuantity = invoice.account.hide_quantity == '1';
    var showItemTaxes = invoice.account.show_item_taxes == '1';

    var grid = [[]];

    if (invoice.has_product_key) {
        grid[0].push({text: invoiceLabels.item, style: ['tableHeader', 'itemTableHeader']});
    }

    grid[0].push({text: invoiceLabels.description, style: ['tableHeader', 'descriptionTableHeader']});

    if (invoice.is_pro && account.custom_invoice_item_label1) {
        grid[0].push({text: account.custom_invoice_item_label1, style: ['tableHeader', 'custom1TableHeader']});
    }
    if (invoice.is_pro && account.custom_invoice_item_label2) {
        grid[0].push({text: account.custom_invoice_item_label2, style: ['tableHeader', 'custom2TableHeader']});
    }

    grid[0].push({text: invoiceLabels.unit_cost, style: ['tableHeader', 'costTableHeader']});

    if (!hideQuantity) {
        grid[0].push({text: invoiceLabels.quantity, style: ['tableHeader', 'qtyTableHeader']});
    }
    if (showItemTaxes) {
        grid[0].push({text: invoiceLabels.tax, style: ['tableHeader', 'taxTableHeader']});
    }

    grid[0].push({text: invoiceLabels.line_total, style: ['tableHeader', 'lineTotalTableHeader']});

    for (var i = 0; i < invoice.invoice_items.length; i++) {

        var row = [];
        var item = invoice.invoice_items[i];
        var cost = formatMoneyInvoice(item.cost, invoice, true);
        var qty = NINJA.parseFloat(item.qty) ? roundToTwo(NINJA.parseFloat(item.qty)) + '' : '';
        var notes = item.notes;
        var productKey = item.product_key;
        var tax = '';        
        
        if (showItemTaxes) {
            if (item.tax && parseFloat(item.tax.rate)) {
                tax = parseFloat(item.tax.rate);
            } else if (item.tax_rate && parseFloat(item.tax_rate)) {
                tax = parseFloat(item.tax_rate);
            }
        }

        // show at most one blank line
        if (shownItem && !notes && !productKey && (!cost || cost == '0' || cost == '0.00' || cost == '0,00')) {
            continue;
        }

        shownItem = true;

        // process date variables
        if (invoice.is_recurring) {
            notes = processVariables(notes);
            productKey = processVariables(productKey);
        }

        var lineTotal = roundToTwo(NINJA.parseFloat(item.cost)) * roundToTwo(NINJA.parseFloat(item.qty));
        lineTotal = formatMoneyInvoice(lineTotal, invoice);

        rowStyle = (i % 2 == 0) ? 'odd' : 'even';
        
        if (invoice.has_product_key) {
            row.push({style:["productKey", rowStyle], text:productKey || ' '}); // product key can be blank when selecting from a datalist
        }
        row.push({style:["notes", rowStyle], stack:[{text:notes || ' '}]}); 
        if (invoice.is_pro && account.custom_invoice_item_label1) {
            row.push({style:["customValue1", rowStyle], text:item.custom_value1 || ' '});
        }
        if (invoice.is_pro && account.custom_invoice_item_label2) {
            row.push({style:["customValue2", rowStyle], text:item.custom_value2 || ' '});
        }
        row.push({style:["cost", rowStyle], text:cost});
        if (!hideQuantity) {
            row.push({style:["quantity", rowStyle], text:qty || ' '});
        }
        if (showItemTaxes) {
            row.push({style:["tax", rowStyle], text:tax ? (tax.toString() + '%') : ' '});
        }
        row.push({style:["lineTotal", rowStyle], text:lineTotal || ' '});

        grid.push(row);
    }   

    return NINJA.prepareDataTable(grid, 'invoiceItems');
}

NINJA.subtotals = function(invoice, hideBalance)
{
    if (!invoice) {
        return;
    }

    var account = invoice.account;
    var data = [];
    data.push([{text: invoiceLabels.subtotal}, {text: formatMoneyInvoice(invoice.subtotal_amount, invoice)}]);

    if (invoice.discount_amount != 0) {
        data.push([{text: invoiceLabels.discount}, {text: formatMoneyInvoice(invoice.discount_amount, invoice)}]);
    }
    
    if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 == '1') {
        data.push([{text: account.custom_invoice_label1}, {text: formatMoneyInvoice(invoice.custom_value1, invoice)}]);
    }
    if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 == '1') {
        data.push([{text: account.custom_invoice_label2}, {text: formatMoneyInvoice(invoice.custom_value2, invoice)}]);
    }

    for (var key in invoice.item_taxes) {
        if (invoice.item_taxes.hasOwnProperty(key)) {
            var taxRate = invoice.item_taxes[key];            
            var taxStr = taxRate.name + ' ' + (taxRate.rate*1).toString() + '%';
            data.push([{text: taxStr}, {text: formatMoneyInvoice(taxRate.amount, invoice)}]);
        }
    }

    if (invoice.tax && invoice.tax.name || invoice.tax_name) {
        var taxStr = invoice.tax_name + ' ' + (invoice.tax_rate*1).toString() + '%';
        data.push([{text: taxStr}, {text: formatMoneyInvoice(invoice.tax_amount, invoice)}]);        
    }

    if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 != '1') {        
        data.push([{text: account.custom_invoice_label1}, {text: formatMoneyInvoice(invoice.custom_value1, invoice)}]);
    }
    if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 != '1') {
        data.push([{text: account.custom_invoice_label2}, {text: formatMoneyInvoice(invoice.custom_value2, invoice)}]);        
    }    

    var paid = invoice.amount - invoice.balance;
    if (invoice.account.hide_paid_to_date != '1' || paid) {
        data.push([{text:invoiceLabels.paid_to_date}, {text:formatMoneyInvoice(paid, invoice)}]);        
    }

    var isPartial = NINJA.parseFloat(invoice.partial);
        
    if (!hideBalance || isPartial) {
        data.push([
            { text: invoiceLabels.balance_due, style: [isPartial ? '' : 'balanceDueLabel'] },
            { text: formatMoneyInvoice(invoice.total_amount, invoice), style: [isPartial ? '' : 'balanceDue'] }
        ]);
    }
    
    if (!hideBalance) {
        if (isPartial) {
            data.push([
                { text: invoiceLabels.partial_due, style: ['balanceDueLabel'] },
                { text: formatMoneyInvoice(invoice.balance_amount, invoice), style: ['balanceDue'] }
            ]);
        }
    }        

    return NINJA.prepareDataPairs(data, 'subtotals');
}

NINJA.subtotalsBalance = function(invoice) {
    var isPartial = NINJA.parseFloat(invoice.partial);
    return [[
        {text: isPartial ? invoiceLabels.partial_due : invoiceLabels.balance_due, style:['balanceDueLabel']},
        {text: formatMoneyInvoice(invoice.balance_amount, invoice), style:['balanceDue']}
    ]];
}

NINJA.accountDetails = function(invoice) {
    var account = invoice.account;
    var data = [
        {text:account.name, style: ['accountName']},
        {text:account.id_number},
        {text:account.vat_number},
        {text:account.website},
        {text:account.work_email},
        {text:account.work_phone}
    ];
    return NINJA.prepareDataList(data, 'accountDetails');
}

NINJA.accountAddress = function(invoice) {
    var account = invoice.account;
    var cityStatePostal = '';    
    if (account.city || account.state || account.postal_code) {
        var swap = account.country && account.country.swap_postal_code;
        cityStatePostal = formatAddress(account.city, account.state, account.postal_code, swap);
    }
    var data = [
        {text: account.address1},
        {text: account.address2},
        {text: cityStatePostal},
        {text: account.country ? account.country.name : ''},
    ];

    if (invoice.is_pro) {
        data.push({text: invoice.account.custom_value1 ? invoice.account.custom_label1 + ' ' + invoice.account.custom_value1 : false});
        data.push({text: invoice.account.custom_value2 ? invoice.account.custom_label2 + ' ' + invoice.account.custom_value2 : false});
    }

    return NINJA.prepareDataList(data, 'accountAddress');
}

NINJA.invoiceDetails = function(invoice) {

    var data = [
    [
        {text: (invoice.is_quote ? invoiceLabels.quote_number : invoiceLabels.invoice_number), style: ['invoiceNumberLabel']},
        {text: invoice.invoice_number, style: ['invoiceNumber']}
    ],
    [
        {text: invoiceLabels.po_number},            
        {text: invoice.po_number}
    ],
    [
        {text:  (invoice.is_quote ? invoiceLabels.quote_date : invoiceLabels.invoice_date)}, 
        {text: invoice.invoice_date}
    ],
    [
        {text: (invoice.is_quote ? invoiceLabels.valid_until : invoiceLabels.due_date)}, 
        {text: invoice.due_date}
    ]
    ];

    if (invoice.custom_text_value1) {
        data.push([
            {text: invoice.account.custom_invoice_text_label1},
            {text: invoice.custom_text_value1}
        ])
    }
    if (invoice.custom_text_value2) {
        data.push([
            {text: invoice.account.custom_invoice_text_label2},
            {text: invoice.custom_text_value2}
        ])
    }

    var isPartial = NINJA.parseFloat(invoice.partial);
    
    if (NINJA.parseFloat(invoice.balance) < NINJA.parseFloat(invoice.amount)) {
        data.push([
            {text: invoiceLabels.balance_due},
            {text: formatMoneyInvoice(invoice.amount, invoice)}
        ]);
    } else if (isPartial) {
        data.push([
            {text: invoiceLabels.balance_due},
            {text: formatMoneyInvoice(invoice.total_amount, invoice)}
        ]);
    }

    data.push([
        {text: isPartial ? invoiceLabels.partial_due : invoiceLabels.balance_due, style: ['invoiceDetailBalanceDueLabel']},
        {text: formatMoneyInvoice(invoice.balance_amount, invoice), style: ['invoiceDetailBalanceDue']}
    ])

    return NINJA.prepareDataPairs(data, 'invoiceDetails');
}

NINJA.clientDetails = function(invoice) {
    var client = invoice.client;
    var data;
    if (!client) {
        return;
    }
    var account = invoice.account;
    var contact = client.contacts[0];
    var clientName = client.name || (contact.first_name || contact.last_name ? (contact.first_name + ' ' + contact.last_name) : contact.email);
    var clientEmail = client.contacts[0].email == clientName ? '' : client.contacts[0].email; 

    var cityStatePostal = '';
    if (client.city || client.state || client.postal_code) {
        var swap = client.country && client.country.swap_postal_code;
        cityStatePostal = formatAddress(client.city, client.state, client.postal_code, swap);
    }

    // if a custom field is used in the invoice/quote number then we'll hide it from the PDF
    var pattern = invoice.is_quote ? account.quote_number_pattern : account.invoice_number_pattern;
    var custom1InPattern = (pattern && pattern.indexOf('{$custom1}') >= 0);
    var custom2InPattern = (pattern && pattern.indexOf('{$custom2}') >= 0);

    data = [
        {text:clientName || ' ', style: ['clientName']},
        {text:client.id_number},
        {text:client.vat_number},
        {text:client.address1},
        {text:client.address2},
        {text:cityStatePostal},
        {text:client.country ? client.country.name : ''},
        {text:clientEmail},
        {text: client.custom_value1 && !custom1InPattern ? account.custom_client_label1 + ' ' + client.custom_value1 : false},
        {text: client.custom_value2 && !custom2InPattern ? account.custom_client_label2 + ' ' + client.custom_value2 : false}
    ];

    return NINJA.prepareDataList(data, 'clientDetails');
}

NINJA.getPrimaryColor = function(defaultColor) {
    return NINJA.primaryColor ? NINJA.primaryColor : defaultColor;
}

NINJA.getSecondaryColor = function(defaultColor) {
    return NINJA.primaryColor ? NINJA.secondaryColor : defaultColor;
}

// remove blanks and add section style to all elements
NINJA.prepareDataList = function(oldData, section) {
    var newData = [];
    for (var i=0; i<oldData.length; i++) {
        var item = NINJA.processItem(oldData[i], section);
        if (item.text || item.stack) {
            newData.push(item);
        }
    }
    return newData;    
}

NINJA.prepareDataTable = function(oldData, section) {
    var newData = [];
    for (var i=0; i<oldData.length; i++) {
        var row = oldData[i];        
        var newRow = [];
        for (var j=0; j<row.length; j++) {
            var item = NINJA.processItem(row[j], section);
            if (item.text || item.stack) {
                newRow.push(item);
            }
        }            
        if (newRow.length) {
            newData.push(newRow);
        }
    }
    return newData;    
}

NINJA.prepareDataPairs = function(oldData, section) {
    var newData = [];
    for (var i=0; i<oldData.length; i++) {
        var row = oldData[i];
        var isBlank = false;
        for (var j=0; j<row.length; j++) {
            var item = NINJA.processItem(row[j], section);
            if (!item.text) {
                isBlank = true;
            }
            if (j == 1) {
                NINJA.processItem(row[j], section + "Value");
            }
        }
        if (!isBlank) {
            newData.push(oldData[i]);
        }
    }
    return newData;
}

NINJA.processItem = function(item, section) {
    if (item.style && item.style instanceof Array) {
        item.style.push(section);
    } else {
        item.style = [section];
    }
    return item;
}


NINJA.parseMarkdownText = function(val, groupText)
{
    var rules = [
        ['\\\*\\\*(\\\w.+?)\\\*\\\*', {'bold': true}], // **value**
        ['\\\*(\\\w.+?)\\\*', {'italics': true}], // *value*
        ['^###(.*)', {'style': 'help'}], // ### Small/gray help
        ['^##(.*)', {'style': 'subheader'}], // ## Header
        ['^#(.*)', {'style': 'header'}] // # Subheader
    ];

    var parts = typeof val === 'string' ? [val] : val;
    for (var i=0; i<rules.length; i++) {
        var rule = rules[i];
        var formatter = function(data) {
            return $.extend(data, rule[1]);
        }
        parts = NINJA.parseRegExp(parts, rule[0], formatter, true);
    }

    return parts.length > 1 ? parts : val;
}

/*
NINJA.parseMarkdownStack = function(val)
{
    if (val.length == 1) {
        var item = val[0];
        var line = item.hasOwnProperty('text') ? item.text : item;

        if (typeof line === 'string') {
            line = [line];
        }
        
        var regExp = '^\\\* (.*[\r\n|\n|\r]?)';
        var formatter = function(data) {
            return {"ul": [data.text]};
        }

        val = NINJA.parseRegExp(line, regExp, formatter, false);
    }
    
    return val;
}
*/

NINJA.parseRegExp = function(val, regExpStr, formatter, groupText)
{
    var regExp = new RegExp(regExpStr, 'gm');
    var parts = [];
    
    for (var i=0; i<val.length; i++) {
        var line = val[i];
        parts = parts.concat(NINJA.parseRegExpLine(line, regExp, formatter, groupText));
    }

    return parts.length > 1 ? parts : val;
}

NINJA.parseRegExpLine = function(line, regExp, formatter, groupText)
{
    var parts = [];
    var lastIndex = 0;
    
    while (match = regExp.exec(line)) {
        if (match.index > lastIndex) {
            parts.push(line.substring(lastIndex, match.index));
        }
        var data = {};
        data.text = match[1];
        data = formatter(data);
        parts.push(data);
        lastIndex = match.index + match[0].length;
    }

    if (parts.length) {
        if (lastIndex < line.length) {
            parts.push(line.substring(lastIndex));
        }
        return parts;
    }

    return line;
}
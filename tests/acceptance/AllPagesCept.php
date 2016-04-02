<?php 

$I = new AcceptanceTester($scenario);
$I->checkIfLogin($I);

$I->wantTo('Test all pages load');

// Top level navigation
$I->amOnPage('/dashboard');
$I->see('Total Revenue');

$I->amOnPage('/clients');
$I->see('Clients', 'li');

$I->amOnPage('/clients/create');
$I->see('Clients', 'li');
$I->see('Create');

$I->amOnPage('/credits');
$I->see('Credits', 'li');

$I->amOnPage('/credits/create');
$I->see('Credits', 'li');
$I->see('Create');

$I->amOnPage('/tasks');
$I->see('Tasks', 'li');

$I->amOnPage('/tasks/create');
$I->see('Tasks', 'li');
$I->see('Create');

$I->amOnPage('/invoices');
$I->see('Invoices', 'li');

$I->amOnPage('/invoices/create');
$I->see('Invoices', 'li');
$I->see('Create');

$I->amOnPage('/quotes');
$I->see('Quotes', 'li');

$I->amOnPage('/quotes/create');
$I->see('Quotes', 'li');
$I->see('Create');

$I->amOnPage('/payments');
$I->see('Payments', 'li');

$I->amOnPage('/payments/create');
$I->see('Payments', 'li');
$I->see('Create');

// Settings pages
$I->amOnPage('/settings/company_details');
$I->see('Details');

$I->amOnPage('/gateways/create');
$I->see('Add Gateway');

$I->amOnPage('/settings/products');
$I->see('Product Settings');

$I->amOnPage('/settings/import_export');
$I->see('Import');

$I->amOnPage('/settings/invoice_settings');
$I->see('Invoice Fields');

$I->amOnPage('/settings/invoice_design');
$I->see('Invoice Design');

$I->amOnPage('/settings/templates_and_reminders');
$I->see('Invoice Email');

$I->amOnPage('/settings/charts_and_reports');
$I->see('Data Visualizations');

$I->amOnPage('/settings/user_management');
$I->see('Add User');

//try to logout
//$I->click('#myAccountButton');
//$I->see('Log Out');
//$I->click('Log Out');

// Miscellaneous pages
$I->amOnPage('/terms');
$I->see('Terms');

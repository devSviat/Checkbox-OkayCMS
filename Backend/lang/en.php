<?php

$lang['sviat__checkbox__title'] = 'Checkbox PRRO';
$lang['sviat__checkbox__settings_saved'] = 'Settings saved';
$lang['sviat__checkbox__settings'] = 'Settings';
$lang['sviat__checkbox__cashier_credentials'] = 'Cashier credentials';
$lang['sviat__checkbox__receipt_settings'] = 'Receipt settings';
$lang['sviat__checkbox__automation_settings'] = 'Automation';
$lang['sviat__checkbox__login'] = 'Login';
$lang['sviat__checkbox__login_placeholder'] = 'Enter cashier login';
$lang['sviat__checkbox__password'] = 'Password';
$lang['sviat__checkbox__password_placeholder'] = 'Enter cashier password';
$lang['sviat__checkbox__license_key'] = 'Cash register license key';
$lang['sviat__checkbox__license_key_placeholder'] = 'Enter cash register license key';
$lang['sviat__checkbox__receipt_text_placeholder'] = 'Text displayed at the bottom of the receipt (use {$order_id} to insert the order number)';
$lang['sviat__checkbox__order_available_status'] = 'Automatically create and send receipt on status change';
$lang['sviat__checkbox__no_status'] = 'No status selected';
$lang['sviat__checkbox__receipt_text'] = 'Receipt text';
$lang['sviat__checkbox__receipt_text_order_id_description'] = 'Text will be displayed at the bottom of the receipt. To insert the order number use:';
$lang['sviat__checkbox__receipt_text_order_id_label'] = 'Order number';
$lang['sviat__checkbox__create_shift'] = 'Open cashier shift';
$lang['sviat__checkbox__close_shift'] = 'Close cashier shift';
$lang['sviat__checkbox__opened_shift'] = 'Shift is open';
$lang['sviat__checkbox__just_created_shift'] = 'Shift created but not yet opened';

$lang['sviat__left_checkbox'] = 'Checkbox PRRO';
$lang['sviat__left_checkbox_settings'] = 'Settings';
$lang['sviat__left_checkbox_taxes'] = 'Tax rates';
$lang['sviat__left_checkbox_shifts'] = 'Shifts';

$lang['sviat__checkbox__refresh'] = 'Check shift';
$lang['sviat__checkbox__show_report'] = 'Show report';
$lang['sviat__checkbox__shifts_no'] = 'No closed shifts';

$lang['sviat__checkbox__shift_opened_at'] = 'Opened';
$lang['sviat__checkbox__shift_closed_at'] = 'Closed';
$lang['sviat__checkbox__shift_status'] = 'Status';

$lang['sviat__checkbox__shift_status_created'] = 'Created';
$lang['sviat__checkbox__shift_status_opening'] = 'Opening';
$lang['sviat__checkbox__shift_status_opened'] = 'Opened';
$lang['sviat__checkbox__shift_status_closing'] = 'Closing';
$lang['sviat__checkbox__shift_status_closed'] = 'Closed';

$lang['sviat__checkbox__errors_empty_params'] = 'Cash register data is not filled in. Register operation is not possible';
$lang['sviat__checkbox__errors_no_shift'] = 'No cashier shift created. Register operation is not possible';
$lang['sviat__checkbox__errors_find_order'] = 'Order not found. Please try again later';
$lang['sviat__checkbox__errors_find_purchases'] = 'Order items not found. Please try again later';
$lang['sviat__checkbox__errors_empty_receipts_isset'] = 'There are unprocessed receipts. Cannot create a new receipt';
$lang['sviat__checkbox__errors_receipt_in_progress'] = 'Receipt is already being created. Please wait';

$lang['sviat__checkbox__order_receipts'] = 'Order receipts';
$lang['sviat__checkbox__order_receipt_create'] = 'Fiscalize receipt';
$lang['sviat__checkbox__order_receipt_create_return'] = 'Create return receipt';

$lang['sviat__checkbox__order_receipt_date'] = 'Receipt date';
$lang['sviat__checkbox__order_receipt_return'] = 'Return';
$lang['sviat__checkbox__order_receipt_print'] = 'Print';

$lang['sviat__checkbox__orders_receipt_pay'] = 'Sale receipt';
$lang['sviat__checkbox__orders_receipt_return'] = 'Return receipt';
$lang['sviat__checkbox__orders_receipt_fiscalized'] = 'Fiscalized';

$lang['sviat__left_checkbox_taxes_add'] = 'Add tax group';
$lang['sviat__left_checkbox_taxes_code'] = 'Code';
$lang['sviat__left_checkbox_taxes_delete'] = 'Delete tax group';
$lang['sviat__left_checkbox_taxes_no'] = 'No tax groups';
$lang['sviat__left_checkbox_taxes_added'] = 'Tax group added';
$lang['sviat__left_checkbox_taxes_updated'] = 'Tax group updated';

$lang['sviat__left_checkbox_taxes_errors_code'] = 'Empty code';
$lang['sviat__left_checkbox_taxes_errors_name'] = 'Empty name';
$lang['sviat__left_checkbox_taxes_errors_exists'] = 'Tax group with this code already exists';

$lang['sviat__left_checkbox_taxes_product_tooltip'] = 'Tax group is required for Checkbox integration';

$lang['sviat__checkbox__shifts'] = 'Cashier shifts';

$lang['sviat__checkbox__type_cash'] = 'Cash';
$lang['sviat__checkbox__type_cashless'] = 'Non-cash payment';

$lang['sviat__checkbox__message_how_send'] = 'Send receipt via';
$lang['sviat__checkbox__message_not_send'] = 'Do not send';
$lang['sviat__checkbox__message_email'] = 'Email';

$lang['sviat__checkbox__label_name'] = 'Checkbox label';
$lang['sviat__checkbox__type'] = 'Checkbox payment type';
$lang['sviat__checkbox__dont_send'] = 'Do not send to Checkbox';
$lang['sviat__checkbox__payment_method_dont_send'] = 'Receipts are not sent to Checkbox for the selected payment method';

$lang['sviat__checkbox__installed_at'] = 'Start date for TTN search when creating receipts';
$lang['sviat__checkbox__installed_at_description'] = 'Receipts will only be created for TTNs updated after this date. Change only if you need to process older orders.';
$lang['sviat__checkbox__installed_at_placeholder'] = 'YYYY-MM-DD HH:MM:SS';

$lang['sviat__checkbox__create_receipt_on_received'] = 'Create receipt when order is received by Nova Poshta';
$lang['sviat__checkbox__create_receipt_on_received_no'] = 'Do not create';
$lang['sviat__checkbox__create_receipt_on_received_yes'] = 'Create automatically';
$lang['sviat__checkbox__create_receipt_on_received_tooltip'] = 'The NovaPoshtaTracking module must be installed and enabled to automatically create receipts when an order is received by Nova Poshta';
$lang['sviat__checkbox__create_receipt_on_received_info_title'] = 'Instructions';
$lang['sviat__checkbox__create_receipt_on_received_info_text'] = 'The NovaPoshtaTracking module is required to automatically create receipts when an order is received by Nova Poshta. Install and enable the module: https://github.com/devSviat/NovaPoshtaTracking-OkayCMS';

$lang['sviat__checkbox__cron_setup_title'] = 'Automatic update setup';
$lang['sviat__checkbox__cron_setup_text_1'] = 'To automatically create receipts when an order is received by Nova Poshta, make sure your server is configured to run the cron task scheduler.';
$lang['sviat__checkbox__cron_setup_text_command'] = 'Add the following command to crontab';
$lang['sviat__checkbox__cron_setup_text_schedule'] = 'every minute (* * * * *)';
$lang['sviat__checkbox__cron_setup_text_2'] = 'Once the cron job is configured, the module will automatically check received orders and create fiscal receipts every 10 minutes: at the 2nd, 12th, 22nd, 32nd, 42nd and 52nd minute of each hour (e.g. 10:02, 10:12, 10:22, 10:32, 10:42, 10:52).';
$lang['sviat__checkbox__cron_setup_copy_hint'] = 'Click to copy';
$lang['sviat__checkbox__cron_setup_copy_copied'] = '✔ Copied to clipboard';
$lang['sviat__checkbox__cron_setup_docs_link'] = 'Detailed instructions for setting up a cron job on your hosting';

$lang['sviat__checkbox__instructions_title'] = 'Setup instructions';
$lang['sviat__checkbox__instructions_registration_title'] = 'Register with Checkbox:';
$lang['sviat__checkbox__instructions_registration_text'] = 'To use the Checkbox software cash register, you must register at my.checkbox.ua, set up the register and cashier, and obtain your credentials.';
$lang['sviat__checkbox__instructions_registration_link'] = 'Detailed registration and setup guide';
$lang['sviat__checkbox__instructions_credentials_title'] = 'Getting login, password and license key:';
$lang['sviat__checkbox__instructions_credentials_text'] = 'After registering at my.checkbox.ua and setting up the register and cashier, you will receive a cashier login and password. The register license key can be found under "Registers" → "Actions" → "Details" in your Checkbox account.';
$lang['sviat__checkbox__instructions_receipt_text_title'] = 'Receipt text:';

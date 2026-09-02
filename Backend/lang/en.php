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
$lang['sviat__checkbox__errors_unknown_source'] = 'Unknown payment source';
$lang['sviat__checkbox__errors_advance_amount'] = 'Advance amount must be greater than zero and less than the goods total';
$lang['sviat__checkbox__confirm_return_chain'] = "Refund the whole payment for this order? Return receipts will be issued to the tax authority. This cannot be undone with a button.";
$lang['sviat__checkbox__errors_chain_is_open'] = "The order is being paid in parts — close it with an after-payment receipt, not a sale receipt";
$lang['sviat__checkbox__errors_advance_not_a_number'] = "Enter the advance amount as a number, for example 500 or 500.50";
$lang['sviat__checkbox__errors_chain_exists'] = "The order already has an advance paid";
$lang['sviat__checkbox__errors_sale_exists'] = "The order is already fiscalised with a sale receipt";
$lang['sviat__checkbox__errors_no_chain'] = "The order has no advance paid";
$lang['sviat__checkbox__errors_chain_closed'] = "The payment for this order is already closed or refunded";
$lang['sviat__checkbox__errors_after_payment_amount'] = 'The after-payment amount must be greater than zero and not exceed the remaining debt';

$lang['sviat__checkbox__order_receipts'] = 'Order receipts';
$lang['sviat__checkbox__order_receipt_create'] = 'Fiscalize receipt';
$lang['sviat__checkbox__order_receipt_create_return'] = 'Create return receipt';

$lang['sviat__checkbox__order_receipt_date'] = 'Receipt date';
$lang['sviat__checkbox__order_receipt_return'] = 'Return';
$lang['sviat__checkbox__order_receipt_print'] = 'Print';

$lang['sviat__checkbox__no_receipts_title'] = "No receipts yet";
$lang['sviat__checkbox__no_receipts_details'] = "Create one below and it will appear in this list.";
$lang['sviat__checkbox__chain_title'] = "Paid in parts";
$lang['sviat__checkbox__chain_status_partial_paid'] = "Advance received, awaiting the rest";
$lang['sviat__checkbox__chain_status_full_paid'] = "Paid in full";
$lang['sviat__checkbox__chain_status_cancelled'] = "Cancelled, receipts returned";
$lang['sviat__checkbox__chain_status_partial_cancelled'] = "Partially cancelled";
$lang['sviat__checkbox__chain_status_unknown'] = "State unknown";
$lang['sviat__checkbox__chain_next_partial_paid'] = "When the rest arrives, close the payment with an after-payment receipt.";
$lang['sviat__checkbox__chain_next_full_paid'] = "Nothing to do: the order is fully fiscalised.";
$lang['sviat__checkbox__chain_next_cancelled'] = "The money has been refunded. The order can now be fiscalised with an ordinary sale receipt.";
$lang['sviat__checkbox__chain_next_unknown'] = "Checkbox did not respond, so actions are hidden to avoid issuing a stray receipt. Reload the page in a minute.";
$lang['sviat__checkbox__np_advance_title'] = "An advance has been paid";
$lang['sviat__checkbox__np_advance_next'] = "Due on pickup";
$lang['sviat__checkbox__chain_paid'] = "Paid";
$lang['sviat__checkbox__chain_of'] = "of";
$lang['sviat__checkbox__chain_left'] = "remaining";
$lang['sviat__checkbox__chain_uah'] = "UAH";
$lang['sviat__checkbox__tip_prepayment'] = "Creates two receipts: the advance now, the after-payment when the rest arrives. The parts may arrive by different means.";
$lang['sviat__checkbox__tip_after_payment'] = "The remainder may arrive by a different route than the advance. The most likely option is preselected from the order's payment method.";
$lang['sviat__checkbox__advance_amount_placeholder'] = "Advance amount, UAH";
$lang['sviat__checkbox__errors_source_not_chosen'] = "Choose where the money came from — this name is printed on the receipt";
$lang['sviat__checkbox__advance_source_label'] = "Where the money came from";
$lang['sviat__checkbox__advance_source_empty'] = "— choose the source of funds —";
$lang['sviat__checkbox__sources_settings'] = "Advance payment sources";
$lang['sviat__checkbox__sources_remove_name'] = "Remove payment system";
$lang['sviat__checkbox__sources_add_name'] = "+ Another payment system";
$lang['sviat__checkbox__sources_name_placeholder'] = "Payment system name, e.g. NovaPay";
$lang['sviat__checkbox__sources_hide_rare'] = "Hide rare ones";
$lang['sviat__checkbox__sources_show_rare'] = "Show rare means of payment";
$lang['sviat__checkbox__sources_form_cashless'] = "Non-cash";
$lang['sviat__checkbox__sources_form_cash'] = "Cash";
$lang['sviat__checkbox__sources_preview'] = "The manager will see";
$lang['sviat__checkbox__sources_help_scenarios'] = "Terminal — \"Картка\". NovaPay, LiqPay, WayForPay — \"Платіж через інтегратора\" with the system name. To the sole trader IBAN: from the customer card — \"Інтернет банкінг\" or \"За реквізитами (IBAN)\"; from the customer account — \"З поточного рахунку\".";
$lang['sviat__checkbox__sources_help_text'] = "The ticked sources make up the list the manager picks from when recording an advance. The chosen label is printed on the receipt as the means of payment.";
$lang['sviat__checkbox__sources_help_link'] = "Checkbox guidance for order No. 601";
$lang['sviat__checkbox__button_prepayment'] = "Issue advance receipt";
$lang['sviat__checkbox__button_after_payment'] = "Close with after-payment receipt";
$lang['sviat__checkbox__button_refresh_chain'] = "Refresh state from Checkbox";
$lang['sviat__checkbox__button_return_advance'] = "Refund the advance";
$lang['sviat__checkbox__button_return_all'] = "Refund the whole payment";
$lang['sviat__checkbox__test_mode_notice'] = "Test cash register: these receipts are not fiscal documents";
$lang['sviat__checkbox__receipt_tag_test'] = "test";
$lang['sviat__checkbox__table_type'] = "Type";
$lang['sviat__checkbox__table_datetime'] = "Date and time";
$lang['sviat__checkbox__table_id'] = "ID";
$lang['sviat__checkbox__table_actions'] = "Actions";
$lang['sviat__checkbox__alert_no_shift_title'] = "No open shift";
$lang['sviat__checkbox__alert_unfinished_title'] = "Unfinished receipts present";
$lang['sviat__checkbox__alert_dont_send_title'] = "Receipt is not sent";
$lang['sviat__checkbox__sale_hidden_by_skip'] = "No sale receipt is offered here: it follows the order's payment method, which is marked \"do not send\". An advance can still be recorded — you state where the money actually came from.";
$lang['sviat__checkbox__chain_open_despite_skip'] = "This order's payment method is marked as \"do not send a receipt\", but an advance has already been paid and the payment still has to be closed.";
$lang['sviat__checkbox__receipt_type_sale'] = 'Sale';
$lang['sviat__checkbox__receipt_type_return'] = 'Return';
$lang['sviat__checkbox__receipt_type_prepayment'] = 'Advance';
$lang['sviat__checkbox__receipt_type_after_payment'] = 'After-payment';
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

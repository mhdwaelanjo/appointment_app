<?php
/**
 * WHMCS Reseller Service API Internal Payment Gateway
 *
 * Used for balance-free ordering. Not visible on the client order form.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function reseller_api_account_MetaData()
{
    return [
        'DisplayName' => 'Reseller API Account',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function reseller_api_account_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Reseller API Account',
        ],
        'UsageNotes' => [
            'Type' => 'System',
            'Value' => 'This gateway is used internally by the Reseller API module for balance-free ordering. It should remain hidden from the standard client area.',
        ],
    ];
}

/**
 * Capture payment (used for tracked mode)
 */
function reseller_api_account_capture($params)
{
    // By default, capture always succeeds for reseller tracked invoices
    return [
        'status' => 'success',
        'transid' => 'RAPI-' . time(),
        'rawdata' => 'Processed via Reseller API',
    ];
}

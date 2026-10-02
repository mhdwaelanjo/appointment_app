<?php
/**
 * WHMCS Reseller Service API Hooks
 */

use WHMCS\Database\Capsule;

add_hook('AfterCronJob', 1, function($vars) {
    // 1. Purge expired nonces
    Capsule::table('mod_rapi_nonces')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();

    // 2. Purge expired idempotency keys
    Capsule::table('mod_rapi_idempotency')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();

    // 3. Process Job Queue
    $jobs = Capsule::table('mod_rapi_jobs')
        ->where('status', 'queued')
        ->where('run_after', '<=', date('Y-m-d H:i:s'))
        ->limit(10)
        ->get();

    foreach ($jobs as $job) {
        // Attempt execution (Simplified)
        Capsule::table('mod_rapi_jobs')->where('id', $job->id)->update([
            'status' => 'succeeded',
            'finished_at' => date('Y-m-d H:i:s')
        ]);

        // Dispatch webhook
        dispatchWebhook($job->reseller_id, 'job.succeeded', ['job_id' => $job->id]);
    }

    // 4. Retry Failed Webhook Deliveries
    $deliveries = Capsule::table('mod_rapi_webhook_deliveries')
        ->whereNull('response_code')
        ->where('next_attempt_at', '<=', date('Y-m-d H:i:s'))
        ->limit(50)
        ->get();

    foreach ($deliveries as $del) {
        $webhook = Capsule::table('mod_rapi_webhooks')->where('id', $del->webhook_id)->first();
        if (!$webhook || $webhook->status !== 'active') continue;

        $secret = \decrypt($webhook->secret_enc);
        $ts = time();
        $sig = 'v1=' . hash_hmac('sha256', $ts . '.' . $del->payload, $secret);

        $ch = curl_init($webhook->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $del->payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Id: ' . $del->event_id,
            'X-Webhook-Event: ' . $del->event,
            'X-Webhook-Signature: t=' . $ts . ',' . $sig
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            Capsule::table('mod_rapi_webhook_deliveries')->where('id', $del->id)->update([
                'response_code' => $code
            ]);
            Capsule::table('mod_rapi_webhooks')->where('id', $webhook->id)->update([
                'failing_since' => null
            ]);
        } else {
            $nextAttempt = $del->attempt + 1;
            if ($nextAttempt <= 8) {
                // Exponential backoff
                $delay = pow(2, $nextAttempt) * 15; // 30s, 60s, 120s...
                Capsule::table('mod_rapi_webhook_deliveries')->where('id', $del->id)->update([
                    'attempt' => $nextAttempt,
                    'next_attempt_at' => date('Y-m-d H:i:s', time() + $delay)
                ]);
            } else {
                Capsule::table('mod_rapi_webhook_deliveries')->where('id', $del->id)->update([
                    'response_code' => $code ?: 0
                ]);
            }
        }
    }
});

function dispatchWebhook($resellerId, $eventName, $data) {
    $webhooks = Capsule::table('mod_rapi_webhooks')
        ->where('reseller_id', $resellerId)
        ->where('status', 'active')
        ->get();

    $eventId = 'evt_' . strtoupper(substr(uniqid(), 0, 16));

    $payload = json_encode([
        'id' => $eventId,
        'event' => $eventName,
        'created_at' => date('Y-m-d\TH:i:s\Z'),
        'sandbox' => false,
        'data' => $data
    ]);

    foreach ($webhooks as $wh) {
        $events = json_decode($wh->events, true) ?: ['*'];
        $match = false;
        foreach ($events as $e) {
            if ($e === '*' || $e === $eventName || (substr($e, -2) === '.*' && strpos($eventName, substr($e, 0, -2)) === 0)) {
                $match = true;
                break;
            }
        }
        if (!$match) continue;

        Capsule::table('mod_rapi_webhook_deliveries')->insert([
            'webhook_id' => $wh->id,
            'event_id' => $eventId,
            'event' => $eventName,
            'payload' => $payload,
            'next_attempt_at' => date('Y-m-d H:i:s')
        ]);
    }
}

add_hook('InvoiceCreated', 1, function($vars) {
    $invoiceId = $vars['invoiceid'];

    // Check if tracked billing mode and gateway is reseller_api_account
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if ($invoice && $invoice->paymentmethod === 'reseller_api_account') {
        // Find reseller for this client
        $reseller = Capsule::table('mod_rapi_resellers')->where('client_id', $invoice->userid)->first();
        if ($reseller) {
            $mode = $reseller->billing_mode;
            if (!$mode) {
                $setting = Capsule::table('mod_rapi_settings')->where('name', 'billing_mode')->first();
                $mode = $setting ? $setting->value : 'free';
            }

            if ($mode === 'tracked') {
                // Auto pay invoice using WHMCS localAPI
                localAPI('AddInvoicePayment', [
                    'invoiceid' => $invoiceId,
                    'transid' => 'RAPI-' . time(),
                    'gateway' => 'reseller_api_account'
                ], 'Reseller API System');
            }
        }
    }
});

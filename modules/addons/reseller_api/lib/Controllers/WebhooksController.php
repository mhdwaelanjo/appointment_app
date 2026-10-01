<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class WebhooksController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function listWebhooks() {
        $webhooks = Capsule::table('mod_rapi_webhooks')
            ->where('reseller_id', $this->key->reseller_id)
            ->get();

        $items = [];
        foreach ($webhooks as $wh) {
            $items[] = [
                'id' => 'wh_' . $wh->id,
                'url' => $wh->url,
                'events' => json_decode($wh->events, true) ?: ['*'],
                'status' => $wh->status,
                'failing_since' => $wh->failing_since ? date('Y-m-d\TH:i:s\Z', strtotime($wh->failing_since)) : null
            ];
        }

        return ['status'=>200, 'data'=>['items'=>$items]];
    }

    public function createWebhook() {
        $body = $this->request['parsed_body'];
        if (empty($body['url']) || !filter_var($body['url'], FILTER_VALIDATE_URL) || strpos($body['url'], 'https://') !== 0) {
            throw new ApiError(400, 'VALIDATION_FAILED', 'A valid HTTPS URL is required');
        }

        $events = isset($body['events']) && is_array($body['events']) ? $body['events'] : ['*'];
        $rawSecret = 'whsec_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 32);

        $id = Capsule::table('mod_rapi_webhooks')->insertGetId([
            'reseller_id' => $this->key->reseller_id,
            'url' => $body['url'],
            'events' => json_encode($events),
            'secret_enc' => \encrypt($rawSecret),
            'status' => 'active'
        ]);

        return ['status'=>201, 'data'=>[
            'id' => 'wh_' . $id,
            'url' => $body['url'],
            'events' => $events,
            'secret' => $rawSecret,
            'status' => 'active'
        ]];
    }

    public function deleteWebhook() {
        $idStr = $this->request['params']['id'];
        $id = (int)str_replace('wh_', '', $idStr);

        $wh = Capsule::table('mod_rapi_webhooks')->where('id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$wh) throw new ApiError(404, 'NOT_FOUND');

        Capsule::table('mod_rapi_webhooks')->where('id', $id)->delete();
        Capsule::table('mod_rapi_webhook_deliveries')->where('webhook_id', $id)->delete();

        return ['status'=>204, 'data'=>[]];
    }

    public function testWebhook() {
        $idStr = $this->request['params']['id'];
        $id = (int)str_replace('wh_', '', $idStr);

        $wh = Capsule::table('mod_rapi_webhooks')->where('id', $id)->where('reseller_id', $this->key->reseller_id)->first();
        if (!$wh) throw new ApiError(404, 'NOT_FOUND');

        $eventId = 'evt_' . strtoupper(substr(uniqid(), 0, 16));
        $payload = json_encode([
            'id' => $eventId,
            'event' => 'ping',
            'created_at' => date('Y-m-d\TH:i:s\Z'),
            'sandbox' => false,
            'data' => ['message' => 'Test event from Reseller API']
        ]);

        Capsule::table('mod_rapi_webhook_deliveries')->insert([
            'webhook_id' => $id,
            'event_id' => $eventId,
            'event' => 'ping',
            'payload' => $payload,
            'next_attempt_at' => date('Y-m-d H:i:s')
        ]);

        return ['status'=>200, 'data'=>['result'=>'queued']];
    }
}

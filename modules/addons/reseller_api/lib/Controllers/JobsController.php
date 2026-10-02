<?php
namespace WHMCS\Module\Addon\ResellerApi\Controllers;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;

class JobsController {
    private $request, $key, $settings;
    public function __construct($req, $k, $s) { $this->request=$req; $this->key=$k; $this->settings=$s; }

    public function getJob() {
        $id = $this->request['params']['id'];

        $job = Capsule::table('mod_rapi_jobs')
            ->where('id', $id)
            ->where('reseller_id', $this->key->reseller_id)
            ->first();

        if (!$job) throw new ApiError(404, 'NOT_FOUND');

        return ['status'=>200, 'data'=>[
            'id' => $job->id,
            'type' => $job->type,
            'status' => $job->status,
            'service_id' => $job->service_id,
            'attempts' => $job->attempts,
            'result' => json_decode($job->result, true),
            'error' => $job->error,
            'created_at' => date('Y-m-d\TH:i:s\Z', strtotime($job->created_at)),
            'finished_at' => $job->finished_at ? date('Y-m-d\TH:i:s\Z', strtotime($job->finished_at)) : null
        ]];
    }
}

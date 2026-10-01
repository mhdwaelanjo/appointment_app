<?php
namespace WHMCS\Module\Addon\ResellerApi\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'mod_rapi_jobs';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['id', 'reseller_id', 'service_id', 'type', 'payload', 'status', 'attempts', 'max_attempts', 'run_after', 'result', 'error', 'finished_at'];
}

<?php
namespace WHMCS\Module\Addon\ResellerApi\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $table = 'mod_rapi_webhooks';
    public $timestamps = false;
    protected $fillable = ['reseller_id', 'url', 'events', 'secret_enc', 'status', 'failing_since'];
}

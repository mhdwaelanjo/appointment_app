<?php
namespace WHMCS\Module\Addon\ResellerApi\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $table = 'mod_rapi_keys';
    public $timestamps = false;
    protected $fillable = ['reseller_id', 'key_id', 'secret_enc', 'prev_secret_enc', 'prev_expires_at', 'fingerprint', 'label', 'scopes', 'environment', 'status', 'allow_global_ip_only', 'expires_at', 'last_used_at', 'last_used_ip', 'created_by_admin'];

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }
}

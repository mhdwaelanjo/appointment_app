<?php
namespace WHMCS\Module\Addon\ResellerApi\Models;

use Illuminate\Database\Eloquent\Model;

class Reseller extends Model
{
    protected $table = 'mod_rapi_resellers';
    protected $fillable = ['client_id', 'name', 'status', 'billing_mode', 'suppress_emails', 'allow_destructive', 'quota_json', 'notes'];

    public function keys()
    {
        return $this->hasMany(ApiKey::class, 'reseller_id');
    }
}

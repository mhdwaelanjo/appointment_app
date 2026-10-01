<?php
namespace WHMCS\Module\Addon\ResellerApi\Security;

use WHMCS\Database\Capsule;

class SecurityPipeline
{
    private $request;
    private $settings;

    public function __construct($request, $settings)
    {
        $this->request = $request;
        $this->settings = $settings;
    }

    public function run()
    {
        $this->checkHttps();
        $this->checkMaintenance();

        $keyId = $this->request['headers']['X-Api-Key'] ?? null;
        if (!$keyId) {
            throw new ApiError(401, 'MISSING_AUTH_HEADERS');
        }

        $this->checkIpWhitelist($keyId);

        $key = $this->lookupKey($keyId);

        $this->checkTimestamp();
        $this->checkNonce($key->id);
        $this->verifySignature($key);

        $this->checkScope($key);
        $this->checkRateLimit($key);
        $this->checkIdempotency($key);

        return $key;
    }

    private function checkHttps()
    {
        if ($this->settings['require_https'] && empty($_SERVER['HTTPS']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https') {
            throw new ApiError(403, 'HTTPS_REQUIRED');
        }
    }

    private function checkMaintenance()
    {
        if ($this->settings['api_enabled'] == '0') {
            throw new ApiError(503, 'API_DISABLED');
        }
        if ($this->settings['maintenance_mode'] == '1' && $this->request['method'] !== 'GET') {
            throw new ApiError(503, 'MAINTENANCE');
        }
    }

    private function checkIpWhitelist($keyId)
    {
        $clientIp = $this->getClientIp();

        $blocked = Capsule::table('mod_rapi_ip_blocks')
            ->where('ip', $clientIp)
            ->where('blocked_until', '>', date('Y-m-d H:i:s'))
            ->exists();

        if ($blocked) {
            throw new ApiError(403, 'IP_NOT_ALLOWED');
        }

        $whitelistEntries = Capsule::table('mod_rapi_ip_whitelist')
            ->where('enabled', 1)
            ->where(function($q) use ($keyId) {
                $q->whereNull('key_id')
                  ->orWhere('key_id', function($q2) use ($keyId) {
                      $q2->select('id')->from('mod_rapi_keys')->where('key_id', $keyId);
                  });
            })
            ->get();

        $allowed = false;
        foreach ($whitelistEntries as $entry) {
            if ($this->ipMatchesCidr($clientIp, $entry->cidr)) {
                $allowed = true;
                Capsule::table('mod_rapi_ip_whitelist')->where('id', $entry->id)->increment('hits', 1, ['last_matched_at' => date('Y-m-d H:i:s')]);
                break;
            }
        }

        if (!$allowed) {
            $this->logDenial($clientIp);
            throw new ApiError(403, 'IP_NOT_ALLOWED');
        }
    }

    private function logDenial($ip) {
        $count = Capsule::table('mod_rapi_audit_log')
            ->where('action', 'ip_denied')
            ->where('ip', $ip)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 60))
            ->count();

        $threshold = $this->settings['auto_block_denials'] ?? 20;

        if ($count + 1 >= $threshold) {
             Capsule::table('mod_rapi_ip_blocks')->insert([
                'ip' => $ip,
                'reason' => 'Auto-blocked due to too many failed requests',
                'blocked_until' => date('Y-m-d H:i:s', time() + 900) // 15 min
            ]);
        }

        Capsule::table('mod_rapi_audit_log')->insert([
            'actor' => 'system',
            'action' => 'ip_denied',
            'target' => $ip,
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function ipMatchesCidr($ip, $cidr) {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);

        if (strpos($ip, ':') !== false) {
            // IPv6
            $ip = inet_pton($ip);
            $subnet = inet_pton($subnet);

            $mask = (int)$mask;
            $bytes = floor($mask / 8);
            $bits = $mask % 8;

            if (substr($ip, 0, $bytes) !== substr($subnet, 0, $bytes)) return false;

            if ($bits > 0) {
                $ipBits = ord($ip[$bytes]) >> (8 - $bits);
                $subnetBits = ord($subnet[$bytes]) >> (8 - $bits);
                if ($ipBits !== $subnetBits) return false;
            }

            return true;
        } else {
            // IPv4
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - (int)$mask);
            $subnet &= $mask;
            return ($ip & $mask) == $subnet;
        }
    }

    private function lookupKey($keyId)
    {
        $key = Capsule::table('mod_rapi_keys')
            ->join('mod_rapi_resellers', 'mod_rapi_resellers.id', '=', 'mod_rapi_keys.reseller_id')
            ->where('mod_rapi_keys.key_id', $keyId)
            ->where('mod_rapi_keys.status', 'active')
            ->where('mod_rapi_resellers.status', 'active')
            ->where(function($q) {
                $q->whereNull('mod_rapi_keys.expires_at')
                  ->orWhere('mod_rapi_keys.expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->select('mod_rapi_keys.*', 'mod_rapi_resellers.client_id', 'mod_rapi_resellers.status as reseller_status')
            ->first();

        if (!$key) {
            throw new ApiError(401, 'INVALID_API_KEY');
        }
        return $key;
    }

    private function checkTimestamp()
    {
        $ts = $this->request['headers']['X-Timestamp'] ?? 0;
        $tolerance = $this->settings['timestamp_tolerance'] ?? 300;

        if (abs(time() - (int)$ts) > $tolerance) {
            throw new ApiError(401, 'TIMESTAMP_OUT_OF_RANGE');
        }
    }

    private function checkNonce($keyInternalId)
    {
        $nonce = $this->request['headers']['X-Nonce'] ?? null;
        if (!$nonce) {
            throw new ApiError(401, 'MISSING_AUTH_HEADERS');
        }

        try {
            Capsule::table('mod_rapi_nonces')->insert([
                'key_id' => $keyInternalId,
                'nonce' => $nonce,
                'expires_at' => date('Y-m-d H:i:s', time() + ($this->settings['nonce_ttl'] ?? 600))
            ]);
        } catch (\Exception $e) {
            throw new ApiError(401, 'NONCE_REUSED');
        }
    }

    private function verifySignature($key)
    {
        $given = $this->request['headers']['X-Signature'] ?? null;
        if (!$given) {
            throw new ApiError(401, 'MISSING_AUTH_HEADERS');
        }

        $canonical = $this->buildCanonicalString();

        // Ensure function decrypt doesn't fail
        $secret = \decrypt($key->secret_enc);
        $expected = 'v1=' . hash_hmac('sha256', $canonical, $secret);

        if (hash_equals($expected, $given)) {
            return;
        }

        if ($key->prev_secret_enc && $key->prev_expires_at > date('Y-m-d H:i:s')) {
            $prevSecret = \decrypt($key->prev_secret_enc);
            $prevExpected = 'v1=' . hash_hmac('sha256', $canonical, $prevSecret);
            if (hash_equals($prevExpected, $given)) {
                return;
            }
        }

        throw new ApiError(401, 'INVALID_SIGNATURE');
    }

    private function buildCanonicalString()
    {
        $method = strtoupper($this->request['method']);
        $path = $this->request['path'];
        $query = $this->request['query'];

        parse_str($query, $pairs);
        ksort($pairs);
        $canonicalQuery = http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);

        $ts = $this->request['headers']['X-Timestamp'];
        $nonce = $this->request['headers']['X-Nonce'];
        $rawBody = $this->request['raw_body'];
        $bodyHash = hash('sha256', $rawBody);

        return implode("\n", [$method, $path, $canonicalQuery, $ts, $nonce, $bodyHash]);
    }

    private function checkScope($key)
    {
        $scopes = json_decode($key->scopes, true) ?: [];
        $requiredScope = $this->request['required_scope'] ?? null;

        if ($requiredScope && !in_array($requiredScope, $scopes) && !in_array('*', $scopes)) {
            throw new ApiError(403, 'INSUFFICIENT_SCOPE');
        }
    }

    private function checkRateLimit($key)
    {
        $count = Capsule::table('mod_rapi_request_log')
            ->where('key_id', $key->id)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 60))
            ->count();

        $limit = $this->settings['rate_limit_per_minute'] ?? 120;

        if ($count >= $limit) {
            throw new ApiError(429, 'RATE_LIMITED');
        }
    }

    private function checkIdempotency($key)
    {
        $idemKey = $this->request['headers']['Idempotency-Key'] ?? null;
        if (!$idemKey || in_array($this->request['method'], ['GET', 'HEAD', 'OPTIONS'])) {
            return;
        }

        $bodyHash = hash('sha256', $this->request['raw_body']);

        $existing = Capsule::table('mod_rapi_idempotency')
            ->where('key_id', $key->id)
            ->where('idem_key', $idemKey)
            ->first();

        if ($existing) {
            if ($existing->body_hash !== $bodyHash) {
                throw new ApiError(409, 'IDEMPOTENCY_CONFLICT');
            }
            if ($existing->status === 'in_progress') {
                throw new ApiError(409, 'REQUEST_IN_PROGRESS');
            }
            throw new IdempotentResponseException($existing->response_code, $existing->response_body);
        }

        Capsule::table('mod_rapi_idempotency')->insert([
            'key_id' => $key->id,
            'idem_key' => $idemKey,
            'body_hash' => $bodyHash,
            'status' => 'in_progress',
            'expires_at' => date('Y-m-d H:i:s', time() + 86400)
        ]);

        $this->request['idem_key'] = $idemKey;
    }

    private function getClientIp()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $proxies = array_filter(array_map('trim', explode(',', $this->settings['trusted_proxies'] ?? '')));
        if (in_array($ip, $proxies) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim(end($parts));
        }
        return $ip;
    }
}

class ApiError extends \Exception
{
    public $statusCode;
    public $errorCode;

    public function __construct($statusCode, $errorCode, $message = '')
    {
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        parent::__construct($message ?: $errorCode);
    }
}

class IdempotentResponseException extends \Exception
{
    public $statusCode;
    public $body;

    public function __construct($statusCode, $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        parent::__construct('IDEMPOTENT_RESPONSE');
    }
}

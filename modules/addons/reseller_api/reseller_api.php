<?php
/**
 * WHMCS Reseller Service API Module
 *
 * @author Jules
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function reseller_api_config()
{
    return [
        'name' => 'Reseller Service API',
        'description' => 'Server-to-server provisioning, management and control of services through a REST API.',
        'author' => 'Jules',
        'language' => 'english',
        'version' => '1.0',
        'fields' => []
    ];
}

function reseller_api_activate()
{
    try {
        $schema = Capsule::schema();

        if (!$schema->hasTable('mod_rapi_resellers')) {
            $schema->create('mod_rapi_resellers', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unique();
                $table->string('name', 191);
                $table->enum('status', ['active', 'suspended', 'disabled']);
                $table->enum('billing_mode', ['free', 'tracked', 'statement'])->nullable();
                $table->tinyInteger('suppress_emails')->default(1);
                $table->tinyInteger('allow_destructive')->default(0);
                $table->text('quota_json')->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (!$schema->hasTable('mod_rapi_keys')) {
            $schema->create('mod_rapi_keys', function ($table) {
                $table->increments('id');
                $table->integer('reseller_id');
                $table->string('key_id', 40)->unique();
                $table->text('secret_enc');
                $table->text('prev_secret_enc')->nullable();
                $table->dateTime('prev_expires_at')->nullable();
                $table->char('fingerprint', 64);
                $table->string('label', 120);
                $table->text('scopes')->nullable(); // JSON
                $table->enum('environment', ['live', 'test']);
                $table->enum('status', ['active', 'disabled', 'revoked']);
                $table->tinyInteger('allow_global_ip_only')->default(0);
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->integer('created_by_admin')->nullable();
            });
        }

        if (!$schema->hasTable('mod_rapi_ip_whitelist')) {
            $schema->create('mod_rapi_ip_whitelist', function ($table) {
                $table->increments('id');
                $table->integer('key_id')->nullable();
                $table->string('cidr', 64);
                $table->binary('ip_start')->nullable(); // varbinary(16) approx
                $table->binary('ip_end')->nullable();
                $table->string('label', 120);
                $table->tinyInteger('enabled')->default(1);
                $table->dateTime('expires_at')->nullable();
                $table->integer('hits')->default(0);
                $table->dateTime('last_matched_at')->nullable();
            });
        }

        if (!$schema->hasTable('mod_rapi_ip_blocks')) {
            $schema->create('mod_rapi_ip_blocks', function ($table) {
                $table->string('ip', 45)->primary();
                $table->string('reason', 191);
                $table->dateTime('blocked_until');
            });
        }

        if (!$schema->hasTable('mod_rapi_nonces')) {
            $schema->create('mod_rapi_nonces', function ($table) {
                $table->integer('key_id');
                $table->char('nonce', 36);
                $table->dateTime('expires_at');
                $table->primary(['key_id', 'nonce']);
                $table->index('expires_at');
            });
        }

        if (!$schema->hasTable('mod_rapi_idempotency')) {
            $schema->create('mod_rapi_idempotency', function ($table) {
                $table->integer('key_id');
                $table->string('idem_key', 128);
                $table->char('body_hash', 64);
                $table->enum('status', ['in_progress', 'done']);
                $table->smallInteger('response_code')->nullable();
                $table->mediumText('response_body')->nullable();
                $table->dateTime('expires_at');
                $table->primary(['key_id', 'idem_key']);
            });
        }

        if (!$schema->hasTable('mod_rapi_product_access')) {
            $schema->create('mod_rapi_product_access', function ($table) {
                $table->increments('id');
                $table->integer('reseller_id')->nullable();
                $table->integer('product_id');
                $table->string('slug', 64);
                $table->enum('service_type', ['shared', 'vps', 'vds', 'mac', 'vmware']);
                $table->string('driver', 40);
                $table->text('allowed_cycles')->nullable(); // JSON
                $table->text('visible_options')->nullable(); // JSON
                $table->text('visible_addons')->nullable(); // JSON
                $table->text('custom_actions')->nullable(); // JSON
            });
        }

        if (!$schema->hasTable('mod_rapi_services')) {
            $schema->create('mod_rapi_services', function ($table) {
                $table->integer('service_id')->primary();
                $table->integer('reseller_id')->index();
                $table->integer('order_id');
                $table->integer('key_id');
                $table->string('external_reference', 128)->index()->nullable();
                $table->string('label', 120)->nullable();
                $table->decimal('list_price', 10, 2)->nullable();
                $table->char('currency', 3)->nullable();
                $table->text('last_error')->nullable();
                $table->dateTime('created_at');
            });
        }

        if (!$schema->hasTable('mod_rapi_jobs')) {
            $schema->create('mod_rapi_jobs', function ($table) {
                $table->char('id', 26)->primary();
                $table->integer('reseller_id');
                $table->integer('service_id');
                $table->string('type', 40);
                $table->text('payload')->nullable(); // JSON
                $table->enum('status', ['queued', 'running', 'succeeded', 'failed', 'cancelled']);
                $table->tinyInteger('attempts')->default(0);
                $table->tinyInteger('max_attempts')->default(3);
                $table->dateTime('run_after')->index();
                $table->text('result')->nullable(); // JSON
                $table->text('error')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('finished_at')->nullable();
            });
        }

        if (!$schema->hasTable('mod_rapi_webhooks')) {
            $schema->create('mod_rapi_webhooks', function ($table) {
                $table->increments('id');
                $table->integer('reseller_id');
                $table->string('url', 500);
                $table->text('events')->nullable(); // JSON
                $table->text('secret_enc');
                $table->enum('status', ['active', 'disabled']);
                $table->dateTime('failing_since')->nullable();
            });
        }

        if (!$schema->hasTable('mod_rapi_webhook_deliveries')) {
            $schema->create('mod_rapi_webhook_deliveries', function ($table) {
                $table->bigIncrements('id');
                $table->integer('webhook_id');
                $table->char('event_id', 26);
                $table->string('event', 60);
                $table->text('payload')->nullable(); // JSON
                $table->tinyInteger('attempt')->default(1);
                $table->smallInteger('response_code')->nullable();
                $table->dateTime('next_attempt_at')->nullable();
            });
        }

        if (!$schema->hasTable('mod_rapi_request_log')) {
            $schema->create('mod_rapi_request_log', function ($table) {
                $table->bigIncrements('id');
                $table->char('request_id', 26)->unique();
                $table->integer('reseller_id')->nullable();
                $table->integer('key_id')->nullable();
                $table->string('ip', 45);
                $table->string('method', 8);
                $table->string('path', 255);
                $table->smallInteger('status');
                $table->string('error_code', 60)->nullable();
                $table->text('pipeline')->nullable(); // JSON
                $table->mediumText('request_body')->nullable();
                $table->mediumText('response_body')->nullable();
                $table->integer('latency_ms');
                $table->dateTime('created_at')->index();
            });
        }

        if (!$schema->hasTable('mod_rapi_audit_log')) {
            $schema->create('mod_rapi_audit_log', function ($table) {
                $table->bigIncrements('id');
                $table->string('actor', 60);
                $table->string('action', 60);
                $table->string('target', 120);
                $table->text('data')->nullable(); // JSON
                $table->string('ip', 45);
                $table->dateTime('created_at');
            });
        }

        if (!$schema->hasTable('mod_rapi_settings')) {
            $schema->create('mod_rapi_settings', function ($table) {
                $table->string('name', 64)->primary();
                $table->text('value')->nullable();
            });

            // Default settings
            Capsule::table('mod_rapi_settings')->insert([
                ['name' => 'api_enabled', 'value' => '0'],
                ['name' => 'maintenance_mode', 'value' => '0'],
                ['name' => 'require_https', 'value' => '1'],
                ['name' => 'timestamp_tolerance', 'value' => '300'],
                ['name' => 'nonce_ttl', 'value' => '600'],
                ['name' => 'rate_limit_per_minute', 'value' => '120'],
                ['name' => 'rate_limit_burst', 'value' => '30'],
                ['name' => 'rate_limit_per_ip', 'value' => '300'],
                ['name' => 'auto_block_denials', 'value' => '20'],
                ['name' => 'trusted_proxies', 'value' => ''],
                ['name' => 'require_key_level_whitelist', 'value' => '1'],
                ['name' => 'billing_mode', 'value' => 'free'],
                ['name' => 'internal_payment_gateway', 'value' => 'reseller_api_account'],
                ['name' => 'suppress_client_emails', 'value' => '1'],
                ['name' => 'provisioning_sync_timeout', 'value' => '25'],
                ['name' => 'job_worker', 'value' => 'cron'],
                ['name' => 'webhook_retries', 'value' => '8'],
                ['name' => 'log_retention', 'value' => '90'],
                ['name' => 'log_request_bodies', 'value' => '1'],
                ['name' => 'strict_json', 'value' => '1'],
                ['name' => 'admin_alert_email', 'value' => ''],
            ]);
        }

        return ['status' => 'success', 'description' => 'Module activated and tables created successfully.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Unable to create tables: ' . $e->getMessage()];
    }
}

function reseller_api_deactivate()
{
    return ['status' => 'success', 'description' => 'Module deactivated. Tables were kept.'];
}

function reseller_api_upgrade($vars)
{
    // Handle database migrations if versions differ
}

function reseller_api_output($vars)
{
    // This is the admin interface logic
    // We'll dispatch to our admin controller
    require_once __DIR__ . '/lib/Admin/Controller.php';
    $controller = new \WHMCS\Module\Addon\ResellerApi\Admin\Controller($vars);
    echo $controller->dispatch();
}

function reseller_api_sidebar($vars)
{
    return <<<EOF
    <div class="sidebar-header">Reseller API</div>
    <ul class="menu">
        <li><a href="addonmodules.php?module=reseller_api">Dashboard</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=resellers">Resellers</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=keys">API Keys</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=ip_whitelist">IP Whitelist</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=products">Product Access</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=settings">Settings</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=logs">Logs</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=jobs">Jobs</a></li>
        <li><a href="addonmodules.php?module=reseller_api&action=webhooks">Webhooks</a></li>
    </ul>
EOF;
}

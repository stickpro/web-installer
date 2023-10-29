<?php
use Merchant\Installer\Api;


require_once __DIR__ . '/api/vendor/autoload.php';

$api = new Api();
$api->request();

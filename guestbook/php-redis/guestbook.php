<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';
require 'Predis/Autoloader.php';

use Jaeger\Config;
use OpenTracing\Formats;
use OpenTracing\Reference;

$config = Config::getInstance();
$tracer = $config->initTracer('example', '0.0.0.0:6831');
$spanContext = $tracer->extract(Formats\TEXT_MAP, $_SERVER);

Predis\Autoloader::register();

if (isset($_GET['cmd']) === true) {
  $serverSpan = $tracer->startSpan("COMMAND {$_GET['cmd']}", ['child_of' => $spanContext]);
  $serverSpan->setTag('command.key', $_GET['key']);

  if (isset($_GET['value'])) {
    $serverSpan->setTag('command.value', $_GET['value']);
  }

  $host = 'redis-master';
  if (getenv('GET_HOSTS_FROM') == 'env') {
    $host = getenv('REDIS_MASTER_SERVICE_HOST');
  }
  header('Content-Type: application/json');
  if ($_GET['cmd'] == 'set') {
    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
    ]);

    $client->set($_GET['key'], $_GET['value']);
    print('{"message": "Updated"}');
  } else {
    $host = 'redis-slave';
    if (getenv('GET_HOSTS_FROM') == 'env') {
      $host = getenv('REDIS_SLAVE_SERVICE_HOST');
    }
    $client = new Predis\Client([
      'scheme' => 'tcp',
      'host'   => $host,
      'port'   => 6379,
    ]);

    $value = $client->get($_GET['key']);
    print('{"data": "' . $value . '"}');
  }

  $serverSpan->finish();
  $config->flush();
} else {
  phpinfo();
} ?>

<?php

require_once __DIR__ . '/../vendor/autoload.php';

use WiziShop\SDK\Exception\ApiException;
use GuzzleHttp\Exception\TransferException;
use WiziShop\SDK\ApiClientFactory;

try {
    $wizishop = ApiClientFactory::authenticate('username', 'password');

    $orders = $wizishop->getOrders();

    // ...
} catch (ApiException $e) {
    echo $e->getErrorMessage();
} catch (TransferException $e) {
    echo $e->getMessage();
}

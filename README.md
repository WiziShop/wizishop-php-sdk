# WiziShop PHP SDK

A PHP SDK (Software Development Kit) for the WiziShop RESTful API [https://api.wizishop.com](https://api.wizishop.com)

## Installation

Get [composer](https://getcomposer.org/)

    composer require wizishop/wizishop-php-sdk

## Usage

Create a new user on [Wizishop Admin](https://admin.wizishop.com/)

```php
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
```

## Tests

    ./bin/phpunit

## Issue Reporting

If you have found a bug or if you have a feature request, please report them at this repository issues section. Please do not report security vulnerabilities on the public GitHub issue tracker.

## Contributing

[Contributing guidelines](CONTRIBUTING.md)

## Author

[WiziShop](https://www.wizishop.com)

## License

This project is licensed under the MIT license. See the [LICENSE](LICENSE) file for more info.

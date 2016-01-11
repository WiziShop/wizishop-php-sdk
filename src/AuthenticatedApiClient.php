<?php

namespace WiziShop\SDK;

use GuzzleHttp\Exception\RequestException;
use WiziShop\SDK\Exception\ApiException;
use WiziShop\SDK\Model\JWT;

class AuthenticatedApiClient extends \GuzzleHttp\Client
{
    /**
     * @const string SDK version
     */
    const VERSION = '1.0.1';

    /**
     * @const string API URL (ending with /)
     */
    const API_URL = 'https://api.wizishop.com/';

    /**
     * @var JWT Json Web Token
     */
    private $jwt;

    public function __construct(JWT $jwt, array $config = [])
    {
        $this->jwt = $jwt;
        $shopId = $jwt->get('id_shop');
        $apiUrl = isset($config['base_uri']) ? $config['base_uri'] : self::API_URL;
        $baseUri = $apiUrl . 'v2/' . ($shopId ? sprintf('shops/%s/', $shopId) : '');

        $defaultConfig = [
            'base_uri' => $baseUri,
            'headers' => [
                'User-Agent' => sprintf('%s wizishop-php-sdk/%s', \GuzzleHttp\default_user_agent(), self::VERSION),
                'Authorization' => 'Bearer ' . $this->jwt->getToken()
            ]
        ];

        parent::__construct($defaultConfig + $config);
    }

    /**
     * @param callable $call Closure to get a json formatted result page for a page number
     * @param callable $parse Closure to get an array of results from the result page
     *
     * @return array The collection of the total pages
     */
    private function assembleResults(callable $call, callable $parse)
    {
        $currentPage = 1;
        $results = [];

        do {
            $resultPage = $call($currentPage);

            if (empty($resultPage)) {
                return [];
            }

            $results = array_merge($results, $parse($resultPage));

            $currentPage++;
        } while ($currentPage <= $resultPage['pages']);

        return $results;
    }

    private function getAllResultsForRoute($route, array $params = [])
    {
        try {
            return $this->assembleResults(
                function ($page) use ($route, $params) {
                    $response = $this->get($route, [
                        'query' => [
                            'limit' => 100,
                            'page' => $page
                        ] + $params
                    ]);

                    return json_decode($response->getBody(), true);
                },
                function ($resultPage) {
                    return $resultPage['results'];
                }
            );
        } catch (RequestException $e) {
            if (404 == $e->getResponse()->getStatusCode()) { // If no result, the API returns 404
                return [];
            }

            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    private function getSingleResultForRoute($route, array $params = [])
    {
        try {
            $response = $this->get($route, $params);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @return JWT
     */
    public function getJWT()
    {
        return $this->jwt;
    }

    public function getBrand($brandId, array $params = [])
    {
        return $this->getSingleResultForRoute(sprintf('brands/%s', $brandId), $params);
    }

    public function getBrands(array $params = [])
    {
        return $this->getAllResultsForRoute('brands', $params);
    }

    public function getSku($sku, array $params = [])
    {
        return $this->getSingleResultForRoute(sprintf('skus/%s', $sku), $params);
    }

    public function getSkus(array $params = [])
    {
        return $this->getAllResultsForRoute('skus', $params);
    }

    public function getDetailedSkus(array $params = [])
    {
        return $this->getAllResultsForRoute('skus', ['detailed' => 1] + $params);
    }

    /**
     * @param string $sku SKU
     * @param int $stock Stock value
     * @param string $method How to update the stock value, can be either "replace" (default), "increase" or "decrease"
     *
     * @return array
     */
    public function updateSkuStock($sku, $stock, $method = 'replace')
    {
        if (!in_array($method, ['replace', 'increase', 'decrease'])) {
            throw new \InvalidArgumentException('Update stock method cannot be ' . $method);
        }

        try {
            $response = $this->patch(sprintf('skus/%s', $sku), [
                'json' => [
                    'method' => $method,
                    'stock' => $stock
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    public function updateBrand($brandId, $newName, $newUrl = null, $newImageUrl = null)
    {
        try {
            $fields = [
                'name' => $newName
            ];

            if ($newUrl) {
                $fields['url'] = $newUrl;
            }

            if ($newImageUrl) {
                $fields['image_url'] = $newImageUrl;
            }

            $response = $this->patch(sprintf('brands/%s', $brandId), [
                'json' => $fields
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    public function createBrand($name, $newImageUrl = null)
    {
        try {
            $fields = [
                'name' => $name
            ];

            if ($newImageUrl) {
                $fields['image_url'] = $newImageUrl;
            }

            $response = $this->post('brands', [
                'json' => $fields
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    public function deleteBrand($brandId)
    {
        try {
            $response = $this->delete(sprintf('brands/%s', $brandId));

            return 204 == $response->getStatusCode();
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @param array $params Array of params
     *                      status_code: Order status code ( abandoned: 0, pending payment: 5, payment awaiting verification: 10, awaiting replenishment: 11, awaiting preparation: 20, preparing: 25, partially sent: 29, sent: 30, delivered: 35, being returned: 40, returned: 45, refunded: 46, canceled: 50)
     *                      start_date: Start date
     *                      end_date: End date
     *
     * @return array
     */
    public function getOrders(array $params = [])
    {
        if (array_key_exists('status_code', $params) && ($params['status_code'] < 0 || $params['status_code'] > 50)) {
            throw new \InvalidArgumentException('Order status code should be between 0 and 50');
        }

        if (array_key_exists('start_date', $params) && $params['start_date'] instanceof \DateTime) {
            $params['start_date'] = $params['start_date']->format('Y-m-d H:i:s');
        }

        if (array_key_exists('end_date', $params) && $params['end_date'] instanceof \DateTime) {
            $params['end_date'] = $params['end_date']->format('Y-m-d H:i:s');
        }

        return $this->getAllResultsForRoute('orders', $params);
    }

    public function getOrder($orderId, array $params = [])
    {
        return $this->getSingleResultForRoute(sprintf('orders/%s', $orderId), $params);
    }

    /**
     * @param $orderId Order id
     * @param array $params
     *
     * @return mixed PDF data to write
     */
    public function getInvoiceForOrder($orderId, array $params = [])
    {
        try {
            $response = $this->get(sprintf('orders/%s/invoice', $orderId), $params);

            return (string) $response->getBody();
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "preparing" (status_code: 25)
     *
     * @param $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function preparingOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/preparing', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "preparing" (status_code: 25)
     *
     * @param $orderId Order id
     * @param array $trackingNumbers Tracking numbers
     *                               Example: [
     *                                   'tracking_numbers' => [
     *                                       [
     *                                           'shipping_id' => 39,
     *                                           'tracking_number' => 'XVBFD-2'
     *                                       ]
     *                                   ]
     *                               ]
     *
     * @return array Order details with the new status
     */
    public function shipOrder($orderId, array $trackingNumbers)
    {
        try {
            $response = $this->post(sprintf('orders/%s/ship', $orderId), [
                'json' => $trackingNumbers
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }
}

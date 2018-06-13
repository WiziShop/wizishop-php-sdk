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
    const VERSION = '1.0.8';

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
        $baseUri = $apiUrl . 'v3/' . ($shopId ? sprintf('shops/%s/', $shopId) : '');

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

    /**
     * @param string $route Route
     * @param array $params
     *
     * @return array Results
     */
    private function getAllResultsForRoute($route, array $params = [])
    {
        try {
            if (array_key_exists('page', $params) || array_key_exists('limit', $params)) {
                return $this->getSingleResultForRoute($route, [
                    'query' => $params
                ]);
            }

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

    /**
     * @param string $route
     * @param array $params
     *
     * @return array Result
     */
    private function getSingleResultForRoute($route, array $params = [])
    {
        try {
            $response = $this->get($route, $params);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if (404 == $e->getResponse()->getStatusCode()) { // If no result, the API returns 404
                return [];
            }

            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @return JWT Json Web Token
     */
    public function getJWT()
    {
        return $this->jwt;
    }

    /**
     * @param int $brandId
     * @param array $params
     *
     * @return array Brand
     */
    public function getBrand($brandId, array $params = [])
    {
        return $this->getSingleResultForRoute(sprintf('brands/%s', $brandId), $params);
    }

    /**
     * @param array $params
     *
     * @return array Brands
     */
    public function getBrands(array $params = [])
    {
        return $this->getAllResultsForRoute('brands', $params);
    }

    /**
     * @param int $customerId
     * @param array $params
     *
     * @return array Customer
     */
    public function getCustomer($customerId, array $params = [])
    {
        return $this->getSingleResultForRoute(sprintf('customers/%s', $customerId), $params);
    }

    /**
     * @param array $params
     *
     * @return array Customers
     */
    public function getCustomers(array $params = [])
    {
        return $this->getAllResultsForRoute('customers', $params);
    }

    /**
     * @param array $params
     *
     * @return array NewsletterSubscribers
     */
    public function getNewsletterSubscribers(array $params = [])
    {
        return $this->getAllResultsForRoute('newsletter/subscribers', $params);
    }

    /**
     * @param string $sku
     * @param array $params
     *
     * @return array Sku
     */
    public function getSku($sku, array $params = [])
    {
        return $this->getSingleResultForRoute(sprintf('skus/%s', rawurlencode($sku)), $params);
    }

    /**
     * @param array $params
     *
     * @return array Skus
     */
    public function getSkus(array $params = [])
    {
        return $this->getAllResultsForRoute('skus', $params);
    }

    /**
     * @param array $params
     *
     * @return array Skus
     */
    public function getDetailedSkus(array $params = [])
    {
        return $this->getAllResultsForRoute('skus', ['detailed' => 1] + $params);
    }

    /**
     * @param string $sku SKU
     * @param int $stock Stock value
     * @param string $method How to update the stock value, can be either "replace" (default), "increase" or "decrease"
     *
     * @return array Sku
     */
    public function updateSkuStock($sku, $stock, $method = 'replace')
    {
        if (!in_array($method, ['replace', 'increase', 'decrease'])) {
            throw new \InvalidArgumentException('Update stock method cannot be ' . $method);
        }

        try {
            $response = $this->patch(sprintf('skus/%s', rawurlencode($sku)), [
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

    /**
     * @param string $brandId
     * @param string $newName
     * @param string $newUrl
     * @param string $newImageUrl
     *
     * @return array Brand
     */
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

    /**
     * @param string $name
     * @param string $newImageUrl
     *
     * @return array Brand
     */
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

    /**
     * @param int $brandId
     *
     * @return bool
     */
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
     * @return array Orders
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
     * @param int $orderId Order id
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
     * @param int $orderId Order id
     * @param array $params
     *
     * @return mixed PDF data to write
     */
    public function getPickingSlipForOrder($orderId, array $params = [])
    {
        try {
            $response = $this->get(sprintf('orders/%s/picking_slip', $orderId), $params);

            return (string) $response->getBody();
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * @param int $orderId Order id
     * @param array $params
     *
     * @return mixed PDF data to write
     */
    public function getDeliverySlipForOrder($orderId, array $params = [])
    {
        try {
            $response = $this->get(sprintf('orders/%s/delivery_slip', $orderId), $params);

            return (string) $response->getBody();
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "pending payment" (status_code: 5)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function pendingPaymentOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/pending_payment', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "pending payment verification" (status_code: 10)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function pendingPaymentVerificationOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/pending_payment_verification', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "pending replenishment" (status_code: 11)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function pendingReplenishmentOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/pending_replenishment', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "pending preparation" (status_code: 20)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function pendingPreparationOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/pending_preparation', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "preparing" (status_code: 25)
     *
     * @param int $orderId Order id
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
     * Changes order status to "partially sent" (status_code: 29)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function partiallySentOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/partially_sent', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "sent" (status_code: 30)
     *
     * @param int $orderId Order id
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

    /**
     * Changes order status to "delivered" (status_code: 35)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function deliveredOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/delivered', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "being returned" (status_code: 40)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function returnOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/return', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "returned" (status_code: 45)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function returnedOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/returned', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }

    /**
     * Changes order status to "refunded" (status_code: 46)
     *
     * @param int $orderId Order id
     *
     * @return array Order details with the new status
     */
    public function refundedOrder($orderId)
    {
        try {
            $response = $this->post(sprintf('orders/%s/refunded', $orderId));

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), $e->getRequest(), $e->getResponse());
        }
    }
}

<?php

namespace WiziShop\SDK\Exception;

use GuzzleHttp\Exception\BadResponseException;

class ApiException extends BadResponseException
{
    public function getErrorMessage()
    {
        $jsonReponse = json_decode($this->getResponse()->getBody(), true);

        if (!$jsonReponse) {
            return null;
        }

        return array_key_exists('message', $jsonReponse) ? $jsonReponse['message'] : '';
    }
}

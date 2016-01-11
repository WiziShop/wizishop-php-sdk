<?php

namespace WiziShop\SDK\Model;

final class JWT
{
    private $token;
    private $payload = [];

    private function __construct() {}

    public static function fromString($token)
    {
        if (empty($token)) {
            throw new \InvalidArgumentException('Token cannot be empty');
        }

        $segments = explode('.', $token);

        if (count($segments) != 3) {
            throw new \UnexpectedValueException('Wrong number of segments');
        }

        list($headb64, $bodyb64) = $segments;

        if (null === ($header = self::jsonDecode(self::urlsafeB64Decode($headb64)))) {
            throw new \UnexpectedValueException('Invalid header encoding');
        }

        if (null === ($payload = self::jsonDecode(self::urlsafeB64Decode($bodyb64)))) {
            throw new \UnexpectedValueException('Invalid claims encoding');
        }

        $jwt = new self();
        $jwt->token = $token;
        $jwt->payload = $payload;

        return $jwt;
    }

    private static function jsonDecode($input)
    {
        return json_decode($input, true);
    }

    private static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    public function get($key, $default = null)
    {
        return isset($this->payload[$key]) ? $this->payload[$key] : $default;
    }

    public function isExpired()
    {
        $now = new \DateTimeImmutable();
        $expiresAt = new \DateTimeImmutable('@' . $this->get('exp', 1));

        return $now > $expiresAt;
    }

    public function getToken()
    {
        return $this->token;
    }
}

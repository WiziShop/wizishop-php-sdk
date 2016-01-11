<?php

namespace WiziShop\SDK\Tests\Model;

use WiziShop\SDK\Model\JWT;

class JWTTest extends \PHPUnit_Framework_TestCase
{
    private $expiredToken;
    private $neverExpiredToken;

    public function setUp()
    {
        $this->expiredToken = sprintf(
            '%s.%s.%s',
            $this->base64encode($this->jsonEncode([
                'alg' => 'RS256',
                'typ' => 'JWS'
            ])),
            $this->base64encode($this->jsonEncode([
                'exp' => (new \DateTimeImmutable())
                    ->sub(\DateInterval::createFromDateString('1 month'))
                    ->getTimestamp(), // expired for a month
                'username' => 'test',
                'id_shop' => 131,
                'ac_shop' => 11,
                'user_id' => 0,
                'iat' => time()
            ])),
            'Signature'
        );

        $this->neverExpiredToken = sprintf(
            '%s.%s.%s',
            $this->base64encode($this->jsonEncode([
                'alg' => 'RS256',
                'typ' => 'JWS'
            ])),
            $this->base64encode($this->jsonEncode([
                'exp' => (new \DateTimeImmutable())
                    ->add(\DateInterval::createFromDateString('30 days'))
                    ->getTimestamp(), // still valid for another 30 days
                'username' => 'test',
                'id_shop' => 131,
                'ac_shop' => 11,
                'user_id' => 0,
                'iat' => time()
            ])),
            'Signature'
        );
    }

    public function testIsExpired()
    {
        $expiredJwt = JWT::fromString($this->expiredToken);
        $this->assertTrue($expiredJwt->isExpired());

        $validJwt = JWT::fromString($this->neverExpiredToken);
        $this->assertFalse($validJwt->isExpired());
    }

    private function base64encode($input)
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function jsonEncode($input)
    {
        return json_encode($input, true);
    }
}

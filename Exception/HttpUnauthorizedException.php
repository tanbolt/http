<?php
namespace Tanbolt\Http\Exception;

use Exception;

/**
 * Class HttpUnauthorizedException
 * @package Tanbolt\Http\Exception
 */
class HttpUnauthorizedException extends HttpException
{
    public function __construct(string $challenge, Exception $previous = null, int $code = 0, string $message = null)
    {
        parent::__construct(401, ['WWW-Authenticate' => $challenge], $previous, $code, $message);
    }
}

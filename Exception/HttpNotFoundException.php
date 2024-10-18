<?php
namespace Tanbolt\Http\Exception;

use Exception;

/**
 * Class HttpNotFoundException
 * @package Tanbolt\Http\Exception
 */
class HttpNotFoundException extends HttpException
{
    public function __construct(array $headers = [], Exception $previous = null, int $code = 0, string $message = null)
    {
        parent::__construct(404, $headers, $previous, $code, $message);
    }
}

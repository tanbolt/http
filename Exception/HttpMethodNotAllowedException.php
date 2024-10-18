<?php
namespace Tanbolt\Http\Exception;

use Exception;

/**
 * Class HttpMethodNotAllowedException
 * @package Tanbolt\Http\Exception
 */
class HttpMethodNotAllowedException extends HttpException
{
    public function __construct(array $allow, Exception $previous = null, int $code = 0, string $message = null)
    {
        parent::__construct(405, ['Allow' => strtoupper(implode(', ', $allow))], $previous, $code, $message);
    }
}

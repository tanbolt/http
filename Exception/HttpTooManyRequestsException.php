<?php
namespace Tanbolt\Http\Exception;

use Exception;

/**
 * Class HttpTooManyRequestsException
 * @package Tanbolt\Http\Exception
 */
class HttpTooManyRequestsException extends HttpException
{
    /**
     * HttpTooManyRequestsException constructor.
     * @param int|string|null $retryAfter 提醒多少秒后再试, 或指定为 GMT 时间字符串
     * @param Exception|null $previous
     * @param int $code
     * @param ?string $message
     */
    public function __construct($retryAfter = null, Exception $previous = null, int $code = 0, string $message = null)
    {
        $headers = [];
        if ($retryAfter) {
            $headers = ['Retry-After' => $retryAfter];
        }
        parent::__construct(429, $headers, $previous, $code, $message);
    }
}

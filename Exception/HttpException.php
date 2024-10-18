<?php
namespace Tanbolt\Http\Exception;

use Exception;
use RuntimeException;
use Tanbolt\Http\Response;

/**
 * Class HttpException
 * @package Tanbolt\Http\Exception
 */
class HttpException extends RuntimeException
{
    private $statusCode;
    private $headers;

    public function __construct(int $statusCode, array $headers = [], Exception $previous = null, int $code = 0, string $message = null)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        parent::__construct($message ?: Response::codeText($statusCode), $code, $previous);
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}

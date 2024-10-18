<?php
namespace Tanbolt\Http\Response;

use Exception;
use ErrorException;
use Tanbolt\Http\Request;
use Tanbolt\Http\Response;
use InvalidArgumentException;

class Json implements FactoryInterface
{
    /**
     * 支持转为 json 的数据
     * @var mixed
     */
    protected $data;

    /**
     * 回调函数
     * @var string
     */
    protected $callback;

    /**
     * 处理后 json 数据
     * @var string
     */
    protected $json;

    /**
     * 是否显示调用 Json 数据时出现的错误
     * @var bool
     */
    protected $jsonErrorShow;

    /**
     * Iterator position
     * @var int
     */
    private $position = 0;

    /**
     * From symfony 3.0
     * Encode <, >, ', &, and " for RFC4627-compliant JSON, which may also be embedded into HTML.
     * 15 === JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
     * @var int
     */
    protected static $encodingOptions = 15;

    /**
     * @inheritDoc
     */
    public function reset()
    {
        $this->data = null;
        $this->json = null;
        $this->callback = null;
        $this->jsonErrorShow = false;
        return $this;
    }

    /**
     * 设置 data
     * @param mixed $data 可以转为 json 的变量
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->json = null;
        return $this;
    }

    /**
     * 获取 data
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * 设置回调函数名称
     * @param ?string $callback
     * @return $this
     */
    public function setCallback(?string $callback)
    {
        if (empty($callback)) {
            $this->callback = null;
            return $this;
        }
        // taken from http://www.geekality.net/2011/08/03/valid-javascript-identifier/
        $pattern = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';
        $parts = explode('.', $callback);
        foreach ($parts as $part) {
            if (!preg_match($pattern, $part)) {
                if ($this->jsonErrorShow) {
                    throw new InvalidArgumentException('The callback name is not valid.');
                }
                $callback = null;
                break;
            }
        }
        $this->callback = $callback;
        return $this;
    }

    /**
     * 获取回调函数名称
     * @return ?string
     */
    public function callback()
    {
        return $this->callback;
    }

    /**
     * 设置是否显示 Json 转换时出现的错误
     * @param bool|true $show
     * @return $this
     */
    public function showJsonError(bool $show = true)
    {
        $this->jsonErrorShow = $show;
        return $this;
    }

     /**
     * 获取当前设置数组的 json 字符串
     * @return string
     * @throws Exception
     */
    public function json()
    {
        try {
            // Clear Last Error
            if (JSON_ERROR_NONE !== json_last_error()) {
                json_encode(null);
            }
            $json = json_encode($this->data, static::$encodingOptions);
            if (JSON_ERROR_NONE !== $code = json_last_error()) {
                if (function_exists('json_last_error_msg')) {
                    $e = json_last_error_msg();
                } else {
                    $e = static::json_last_error_msg($code);
                }
                throw new ErrorException($e);
            }
        } catch (Exception $e) {
            // 考虑到输出 json 一般是在 JS 中使用
            // 默认情况下 如果有错 则输出 null
            if ($this->jsonErrorShow) {
                throw $e;
            }
            $json = json_encode(null);
        }
        return $json;
    }

    /**
     * json_last_error_msg
     * @param $code
     * @return string
     */
    private static function json_last_error_msg($code)
    {
        switch ($code) {
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded.';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch.';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found.';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON.';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded.';
            default:
                return 'Unknown error.';
        }
    }

    /**
     * 获取 Jsonp 字符串
     * @return string
     * @throws
     */
    public function jsonp()
    {
        if (null === $this->json) {
            $this->json = $this->json();
        }
        if ($this->callback) {
            return sprintf('/**/%s(%s);', $this->callback, $this->json);
        }
        return $this->json;
    }

    /**
     * 设置 Response Header
     * @param Response $response
     * @param Request|null $request
     * @return $this
     */
    public function prepare(Response $response, Request $request = null)
    {
        $response->setHeader('Content-Length', strlen($this->jsonp()));
        if (null !== $this->callback) {
            $response->setHeader('Content-Type', 'text/javascript');
        } elseif (!($type = $response->getHeader('Content-Type')) || 'text/javascript' === $type) {
            $response->setHeader('Content-Type', 'application/json');
        }
        return $this;
    }

    /**
     * 以下为 Iterator 接口实现
     * @return $this
     */
    public function rewind()
    {
        $this->position = 0;
        return $this;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->position < 1;
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @return string
     */
    public function current()
    {
        return $this->valid() ? $this->jsonp() : null;
    }

    /**
     * @return $this
     */
    public function next()
    {
        $this->position++;
        return $this;
    }
}

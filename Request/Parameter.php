<?php
namespace Tanbolt\Http\Request;

use Iterator;
use Countable;
use ArrayAccess;
use SimpleXMLElement;

class Parameter implements Iterator, ArrayAccess, Countable
{
    /**
     * 类型
     * @var ?string
     */
    private $parameterType;

    /**
     * 参数容器
     * @var array
     */
    protected $parameters;

    /**
     * Parameter constructor.
     * @param array $parameters
     * @param ?string $parameterType
     */
    public function __construct(array $parameters = [], string $parameterType = null)
    {
        $this->parameterType = $parameterType;
        $this->reset($parameters);
    }

    /**
     * 重置参数
     * @param array $parameters
     * @return $this
     */
    public function reset(array $parameters = [])
    {
        $this->parameters = [];
        return $this->set($parameters);
    }

    /**
     * 设置一个或一组参数(默认覆盖)
     * @param array|string $key string:键值;  array:一组键值对
     * @param array|string|null $value
     * @param bool $replace 对已存在的参数是否进行覆盖
     * @return $this
     */
    public function set($key, $value = null, bool $replace = true)
    {
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->setParam($k, $v, $replace);
            }
            return $this;
        }
        return $this->setParam($key, $value, $replace);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param bool $replace
     * @return $this
     */
    private function setParam(string $key, $value = null, bool $replace = true)
    {
        if (($key = $this->preparedKey($key)) && ($replace || !isset($this->parameters[$key]))) {
            $this->parameters[$key] = $this->preparedSetValue($key, $value);
        }
        return $this;
    }

    /**
     * 设置一个或一组参数(不覆盖)
     * @param string|array $key
     * @param string|array|null $value
     * @return Parameter
     */
    public function setIf($key, $value = null)
    {
        return $this->set($key, $value, false);
    }

    /**
     * 是否包含指定 $key
     * @param string|array $key 可指定为数组或字符串
     * @param bool $any 若 $key 为数组，是否包含任意一个即可
     * @return bool
     */
    public function has($key, bool $any = false)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            if (($k = $this->preparedKey($k)) && array_key_exists($k, $this->parameters)) {
                if ($any) {
                    return true;
                }
            } elseif (!$any) {
                return false;
            }
        }
        return !$any;
    }

    /**
     * 获取指定键值参数
     * @param string|string[] $key string:指定键值; array:返回数组中最先匹配到的键值参数
     * @param mixed $default 获取失败的默认值
     * @return array|string|null
     */
    public function get($key, $default = null)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                if ($value = $this->get($k)) {
                    return $value;
                }
            }
            return $default;
        }
        return ($key = $this->preparedKey($key)) && array_key_exists($key, $this->parameters)
            ? $this->preparedGetValue($key, $this->parameters[$key])
            : $default;
    }

    /**
     * 移除一个或一组参数
     * @param string|array $key 可设置为数组同时移除多个
     * @return $this
     */
    public function remove($key)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $this->remove($k);
            }
        } else {
            if (($key = $this->preparedKey($key)) && isset($this->parameters[$key])) {
                $this->preparedRemValue($key, $this->parameters[$key]);
                unset($this->parameters[$key]);
            }
        }
        return $this;
    }

    /**
     * 扩展预留: 解析重置 $key 值 (如 Header 参数的 "- _" 字符兼容处理)
     * @param string $key
     * @return string
     */
    protected function preparedKey(string $key)
    {
        return $key;
    }

    /**
     * 扩展预留: 当获取 $key (已 prepared) 的 $value 时对其进行重置
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function preparedGetValue(string $key, $value)
    {
        return $value;
    }

    /**
     * 扩展预留: 当设置 $key (已 prepared) 的 $value 时对其进行重置， 如 File 将上传文件转为对象
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function preparedSetValue(string $key, $value)
    {
        return $value;
    }

    /**
     * 扩展预留: 当删除 $key (已 prepared) 的 $value 时
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    protected function preparedRemValue(string $key, $value)
    {
        return $this;
    }

    /**
     * 参数总数
     * @return int
     */
    public function count()
    {
        return count($this->parameters);
    }

    /**
     * 所有键值
     * @return array
     */
    public function keys()
    {
        return array_keys($this->parameters);
    }

    /**
     * 所有参数
     * @return array
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * 清空所有参数
     * @return $this
     */
    public function clear()
    {
        $this->parameters = [];
        return $this;
    }

    /**
     * @return $this
     */
    public function rewind()
    {
        return reset($this->parameters);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return null !== key($this->parameters);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return current($this->parameters);
    }

    /**
     * @return string
     */
    public function key()
    {
        return key($this->parameters);
    }

    /**
     * @return $this
     */
    public function next()
    {
        return next($this->parameters);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return ?string
     */
    public function offsetGet($offset)
    {
        if (!$this->has($offset)) {
            $trace = debug_backtrace();
            trigger_error('Undefined index: '.$offset.' in '.$trace[0]['file'].' on line '.$trace[0]['line'], E_USER_NOTICE);
        }
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * 魔术方法输出字符串形式
     * @return string
     */
    public function __toString()
    {
        if (!$this->parameters) {
            return '';
        }
        switch ($this->parameterType) {
            case 'query':
            case 'request':
            case 'attributes':
                return http_build_query($this->parameters);
            case 'cookie':
                $cookies = [];
                foreach ($this->parameters as $key => $parameter) {
                    $cookies[] = $key.'='.$parameter;
                }
                return implode('; ', $cookies);
            case 'json':
                return json_encode($this->parameters);
            case 'xml':
                return static::arrayToXml($this->parameters);
        }
        return '';
    }

    /**
     * array 转 XML
     * @param array $array
     * @param SimpleXMLElement|null $xml
     * @return string
     */
    protected static function arrayToXml(array $array, SimpleXMLElement $xml = null)
    {
        $xml = $xml ?: new SimpleXMLElement('<root/>');
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                static::arrayToXml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, $value);
            }
        }
        return $xml->asXML();
    }
}

<?php
namespace Tanbolt\Http\Request;

use DateTime;
use DateTimeZone;
use DateTimeInterface;

class Header extends Parameter
{
    /**
     * cacheControl 容器
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9
     * @var array
     */
    protected $cacheControl = [];

    /**
     * 设置 cacheControl 时使用的临时标识符
     * @var bool
     */
    private $setControlInside = false;

    /**
     * @param array $parameters
     * @return $this
     */
    public function reset(array $parameters = [])
    {
        $this->cacheControl = [];
        $this->setControlInside = false;
        parent::reset($parameters);
        return $this;
    }

    /**
     * Http Header 允许同键值头, 对于同一个 key，可多次添加 value
     * @param string $key
     * @param string|array $value
     * @return $this
     */
    public function add(string $key, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        if ($this->has($key)) {
            $value = array_merge($this->get($key), $value);
        }
        return $this->set($key, $value);
    }

    /**
     * 获取指定 key 第一个值 header 值
     * @param string|array $key
     * @param ?string $default
     * @return ?string
     */
    public function getFirst($key, string $default = null)
    {
        return ($header = $this->get($key)) ? array_shift($header) : $default;
    }

    /**
     * 获取指定 key 最后一个值 header 值
     * @param string|array $key
     * @param ?string $default
     * @return ?string
     */
    public function getLast($key, string $default = null)
    {
        return ($header = $this->get($key)) ? array_pop($header) : $default;
    }

    /**
     * 获取指定 key 的 header string
     * @param string|array $key
     * @param ?string $default
     * @return ?string
     */
    public function getLine($key, string $default = null)
    {
        return ($header = $this->get($key)) ? implode(',', $header) : $default;
    }

    /**
     * 设置指定 key 为一个 时间格式的 header 头
     * @param string $key
     * @param DateTimeInterface|string|int $value DateTime对象 或 日期字符串 或 Unix时间戳
     * @return $this
     * @throws
     */
    public function setDate(string $key, $value)
    {
        if ($value instanceof DateTimeInterface) {
            $date = clone $value;
        } else {
            $date = new DateTime(is_int($value) ? '@'.$value : $value);
        }
        $date->setTimezone(new DateTimeZone('UTC'));
        $this->set($key, $date->format(DATE_RFC7231));
        return $this;
    }

    /**
     * 获取时间格式的数据  返回 DateTime Object, 如:
     * - request  中的 Date If-Modified-Since；
     * - response 中的 Date Expires Last-Modified；
     * @param string|array $key
     * @param DateTimeInterface|string|int|null $default
     * @return ?DateTimeInterface
     * @throws
     */
    public function getDate($key, $default = null)
    {
        $value = $this->getLast($key);
        $value = null === $value ? $default : $value;
        // $default 为 null
        if (!$value) {
            return $value;
        }
        // $date 或 $default 已是 DateTimeInterface
        if ($value instanceof DateTimeInterface) {
            if ('UTC' !== $value->getTimezone()->getName()) {
                $value->setTimezone(new DateTimeZone('UTC'));
            }
            return $value;
        }
        // 尝试转换 $data 或 $default 为 DateTimeInterface
        return new DateTime(is_int($value) ? '@'.$value : $value, new DateTimeZone('UTC'));
    }

    /**
     * 继承父级 并进行实现, 根据 HTTP 协议, Key 值为大小写敏感。
     * 目前看来, 常规写法仍然是首字母大写, 实际使用中应避免大小写同名混用
     * @param string $key
     * @return string
     */
    protected function preparedKey(string $key)
    {
        $key = str_replace('_', '-', strtolower($key));
        return implode('-', array_map('ucfirst', explode('-', $key)));
    }

    /**
     * 继承父级, 设置 Header，value 保存为 array；若赶巧是设置 cacheControl，特殊处理
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function preparedSetValue(string $key, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        // 由外部触发的 Cache-Control 设置, 提取数组
        if (!$this->setControlInside && 'Cache-Control' === $key) {
            $this->cacheControl = static::convertCacheControlToArray(implode(',', $value));
        }
        return $value;
    }

    /**
     * 继承父级, 清空 Header 赶巧同时清空 cacheControl
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    protected function preparedRemValue(string $key, $value)
    {
        if ('Cache-Control' === $key) {
            $this->cacheControl = [];
        }
        parent::preparedRemValue($key, $value);
        return $this;
    }

    /**
     * cache-control 是否包含指定 key
     * @param string|array $key 可指定为数组或字符串
     * @param bool $any 若 $key 为数组，是否包含任意一个即可
     * @return bool
     */
    public function hasCacheControl($key, bool $any = false)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            if (($k = static::preparedCacheControlKey($k)) && array_key_exists($k, $this->cacheControl)) {
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
     * 添加数据到 cache-control
     * @param array|string $key
     * @param string|bool $value
     * @return $this
     */
    public function setCacheControl($key, $value = true)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->cacheControl[static::preparedCacheControlKey($k)] = $v;
            }
        } else {
            $this->cacheControl[static::preparedCacheControlKey($key)] = $value;
        }
        return $this->refreshHeader();
    }

    /**
     * 获取指定的 cache-control 数据 (一个 或 有优先级的一组)
     * @param array|string $key
     * @param ?string $default
     * @return ?string
     */
    public function getCacheControl($key, string $default = null)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                if ($return = $this->getCacheControl($k)) {
                    return $return;
                }
            }
            return $default;
        }
        return ($key = static::preparedCacheControlKey($key)) && array_key_exists($key, $this->cacheControl)
            ? $this->cacheControl[$key]
            : $default;
    }

    /**
     * 删除指定的 cache-control
     * @param array|string $key
     * @return $this
     */
    public function removeCacheControl($key)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                unset($this->cacheControl[static::preparedCacheControlKey($k)]);
            }
        } else {
            unset($this->cacheControl[static::preparedCacheControlKey($key)]);
        }
        return $this->refreshHeader();
    }

    /**
     * cache-control 数目
     * @return int
     */
    public function countCacheControl()
    {
        return count($this->cacheControl);
    }

    /**
     * 所有 cache-control 属性
     * @return array
     */
    public function allCacheControl()
    {
        return $this->cacheControl;
    }

    /**
     * 清空所有 CacheControl
     * @return $this
     */
    public function clearCacheControl()
    {
        $this->cacheControl = [];
        return $this->refreshHeader();
    }

    /**
     * 内部设置 CacheControl -> 更新 Cache-Control header -> 会触发转 string 为 array
     * > 由于内部设置已经处理过 cacheControl 数组了, 所以导致该步骤无必要, 这里使用一个内部变量以做区分
     * @return $this
     */
    protected function refreshHeader()
    {
        $this->setControlInside = true;
        $cacheControl = $this->convertCacheControlToHeader();
        if (empty($cacheControl)) {
            $this->remove('Cache-Control');
        } else {
            $this->set('Cache-Control', $cacheControl);
        }
        $this->setControlInside = false;
        return $this;
    }

    /**
     * prepared cache-control key
     * @param string $key
     * @return string
     */
    protected static function preparedCacheControlKey(string $key)
    {
        return strtolower(str_replace('_', '-', strtolower($key)));
    }

    /**
     * 从 Header 中的 cache-control 字符串 解析出数组
     * @param string $header
     * @return array
     */
    protected static function convertCacheControlToArray(string $header)
    {
        $cacheControl = [];
        if ($header != '') {
            preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $cacheControl[strtolower($match[1])] = $match[3] ?? ($match[2] ?? true);
            }
        }
        return $cacheControl;
    }

    /**
     * 将 cache-control 转为可输出为 Header 的字符串
     * @param ?array $array
     * @return string
     */
    protected function convertCacheControlToHeader(array $array = null)
    {
        $array = $array ?: $this->cacheControl;
        if (!count($array)) {
            return '';
        }
        $header = [];
        ksort($array);
        foreach ($array as $key => $value) {
            if (true === $value) {
                $header[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"' . $value . '"';
                }
                $header[] = "$key=$value";
            }
        }
        return implode(',', $header);
    }

    /**
     * clear Header 同时 clear cacheControl
     * @return $this
     */
    public function clear()
    {
        $this->cacheControl = [];
        parent::clear();
        return $this;
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
        $content = '';
        ksort($this->parameters);
        foreach ($this->parameters as $key => $value) {
            if ('Set-Cookie' === $key || 'Warning' === $key) {
                foreach ($value as $val) {
                    $content .= $key.': '.$val."\r\n";
                }
            } else {
                $content .= $key.': '.join(',', (array) $value)."\r\n";
            }
        }
        return $content;
    }
}

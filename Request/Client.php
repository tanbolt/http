<?php
namespace Tanbolt\Http\Request;

/**
 * Class Client: 分析 UserAgent 工具箱
 * > 获取客户端信息，推荐在前端使用 js 根据环境特性判断
 * 但在某些不方便的情况下，可以通过该工具分析 UA 获取
 * @package Tanbolt\Http\Request
 */
class Client
{
    // 客户端设备类型
    const DEVICE_UNKNOWN = -1;
    const DEVICE_DESKTOP = 0;
    const DEVICE_MOBILE = 1;
    const DEVICE_PHONE = 2;
    const DEVICE_TABLET = 3;
    const DEVICE_CONSOLE = 4;
    const DEVICE_TV = 5;

    // Mobile 设备 OS 操作系统类型
    const SYSTEM_ANDROID = 'Android';
    const SYSTEM_IOS = 'iOS';
    const SYSTEM_WINDOWS_PHONE = 'WindowsPhone';
    const SYSTEM_WINDOWS_MOBILE = 'WindowsMobile';
    const SYSTEM_BLACKBERRY = 'BlackBerry';
    const SYSTEM_PLAYBOOK = 'PlayBook';
    const SYSTEM_TIZEN = 'Tizen';
    const SYSTEM_FIREFOXOS = 'FirfoxOS';
    const SYSTEM_SYMBIAN = 'Symbian';
    const SYSTEM_SAILFISH = 'Sailfish';
    const SYSTEM_MEEGO = 'MeeGo';
    const SYSTEM_MEAGO = 'Maemo';
    const SYSTEM_PALM = 'Palm';
    const SYSTEM_BREW = 'Brew';
    const SYSTEM_BADA = 'Bada';
    const SYSTEM_JAVA = 'Java';

    // Desktop 设备 OS 操作系统类型
    const SYSTEM_WINDOWS = 'Windows';
    const SYSTEM_MACINTOSH = 'Macintosh';
    const SYSTEM_UNIX = 'Unix';

    // TV 设备 OS 操作系统类型
    const SYSTEM_GOOGLETV = 'GoogleTV';
    const SYSTEM_HBBTV = 'HbbTV';
    const SYSTEM_WEBTV = 'WebTV';

    // Console 设备 OS 操作系统类型
    const SYSTEM_PLAYSTATION = 'PlayStation';
    const SYSTEM_NINTENDO = 'Nintendo';

    // 浏览器渲染引擎
    const BROWSER_BLINK = 'Blink';
    const BROWSER_EDGE = 'Edge';
    const BROWSER_TRIDENT = 'Trident';
    const BROWSER_WEBKIT = 'Webkit';
    const BROWSER_GECKO = 'Gecko';
    const BROWSER_PRESTO = 'Presto';
    const BROWSER_NETFRONT = 'NetFront';
    const BROWSER_KHTML = 'KHTML';

    /**
     * 移动设备常用 Header
     * Code From Mobile Detect Library
     * @author      Current authors: Serban Ghita <serbanghita@gmail.com>
     *                               Nick Ilyin <nick.ilyin@gmail.com>
     * @link  https://github.com/serbanghita/Mobile-Detect/blob/2.8.20/Mobile_Detect.php
     * @var array
     */
    protected static $mobileHeaders = [
        'ACCEPT'                  => [
            'matches' => [
                // Opera Mini; @reference: http://dev.opera.com/articles/view/opera-binary-markup-language/
                'application/x-obml2d',
                // BlackBerry devices.
                'application/vnd.rim.html',
                'text/vnd.wap.wml',
                'application/vnd.wap.xhtml+xml'
            ]
        ],
        'X_WAP_PROFILE'           => null,
        'X_WAP_CLIENTID'          => null,
        'WAP_CONNECTION'          => null,
        'PROFILE'                 => null,
        // Reported by Opera on Nokia devices (eg. C3).
        'X_OPERAMINI_PHONE_UA'    => null,
        'X_NOKIA_GATEWAY_ID'      => null,
        'X_ORANGE_ID'             => null,
        'X_VODAFONE_3GPDPCONTEXT' => null,
        'X_HUAWEI_USERID'         => null,
        // Reported by Windows Smartphones.
        'UA_OS'                   => null,
        // Reported by Verizon, Vodafone proxy system.
        'X_MOBILE_GATEWAY'        => null,
        // Seen this on HTC Sensation. SensationXE_Beats_Z715e.
        'X_ATT_DEVICEID'          => null,
        // Seen this on a HTC.
        'UA_CPU'                  => ['matches' => ['ARM']],
    ];

    /**
     * robot 常见规则
     * @var array
     */
    protected static $robotSpider = [
        // http://baidu.com/search/spider.htm
        'Baidu'  => 'Baiduspider-(image|video|news|favo|cpro|ads)|Baiduspider',
        // https://support.google.com/webmasters/answer/1061943?hl=zh-Hans
        'Google' => 'Googlebot-(News|Image|Video|Mobile)|Mediapartners-Google|Mediapartners|AdsBot-Google|Googlebot',
        // https://www.bing.com/webmaster/help/which-crawlers-does-bing-use-8c184ec0
        'bing'   => 'MSNBot-Media|MSNBot|AdIdxBot|BingPreview|Bingbot',
        // https://help.yahoo.com/kb/SLN22600.html
        'yahoo'  => 'Slurp',
        // https://www.so.com/help/help_3_2.html
        '360so'  => 'HaoSouSpider|360Spider-(Image|Video)|360Spider',
        // http://zhanzhang.sogou.com/index.php/help/pachong 实测发现搜狗帮助文档中列出的 spider 不完整
        'sogou'  => 'Sogou.*spider|Sogou blog',
        // http://www.youdao.com/help/webmaster/robot/004/
        'youdao' => 'YoudaoBot',
    ];

    /**
     * 浏览器引擎
     * @var array
     */
    protected static $browserEngines = [
        [self::BROWSER_BLINK, 'Blink[ /]([0-9.]+)?', 1],
        [self::BROWSER_EDGE, 'Edge[ /]([0-9.]+)?', 1],
        [self::BROWSER_TRIDENT, 'Trident[ /]([0-9.]+)?', 1],
        [self::BROWSER_WEBKIT, 'Version/([0-9.]+).*(?:Apple)?WebKit', 1],
        [self::BROWSER_WEBKIT, '(?:Apple)?WebKit[ /]([0-9.]+)?', 1],
        [self::BROWSER_GECKO, '(?<!like )Gecko[ /]([0-9.]+)?', 1],
        [self::BROWSER_PRESTO,'Presto[ /]([0-9.]+)?', 1],
        [self::BROWSER_NETFRONT, 'NetFront[ /]([0-9.]+)?', 1],
        [self::BROWSER_KHTML, 'KHTML[ /]([0-9.]+)?', 1],
    ];

    /**
     * 移动终端浏览器规则
     * @var array
     */
    protected static $mobileBrowsers = [
        ['UC', 'UC[ ]?Browser(?:[ /]?([0-9.]+))?', 1],
        ['UC', 'UCWEB(?:[ /]?([0-9.]+))?', 1],
        ['QQ', 'MQQBrowser(?:/(?:Mini)?([0-9.]+))?', 1],
        ['MIUI', 'MIUIBrowser(?:/([0-9.]+))?', 1],
        ['Samsung', 'SamsungBrowser(?:[ /]([0-9.]+))?', 1],
        ['Baidu', 'baiduBrowser(?:[/ ]([0-9.]+))?', 1],
        ['360', '360 Aphone Browser(?: \(([0-9.]+)(?:beta)?\))', 1],
        ['Maxthon', 'MxBrowser(?:[ /]([0-9.]+))?', 1],
        ['LieBao', '(LBBrowser|LieBaoFast)(?:[ /]([0-9.]+))?', 2],
        ['Sougou', 'SogouMobileBrowser(?:[ /]([0-9.]+))?', 1],
        ['Generic', '(NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision)([ /]([0-9.]+))?', 3],
        ['Opera', '(Opera|OPR|Coast)([^/]+)?(?:[ /]([0-9.]+))?', 3],
        ['Firefox', '(Firefox|BonEcho|GranParadiso|Lorentz|Minefield|Namoroka|Shiretoko|FxiOS)(?:[ /]([0-9.]+))?', 2],
        ['Chrome', '(Chrome|CrMo|CriOS)(?:[ /]([0-9.]+))?', 2],
        ['IE', '(IEMobile|MSIEMobile|MSIE)[ /]([0-9.]+)?', 2],
        ['Safari', 'Version/([0-9.]+).*Safari/', 1],
        ['Safari', 'Safari/([0-9.]+)?', 1],
    ];

    /**
     * 桌面终端浏览器规则
     * @var array
     */
    protected static $desktopBrowsers = [
        ['QQ', 'QQBrowser(?:/(?:Mini)?([0-9.]+))?', 1],
        ['Maxthon', 'Maxthon(?:/(?:Mini)?([0-9.]+))?', 1],
        ['Chromium', 'Chromium(?:/([0-9.]+))?', 1],
        ['Chrome', 'Chrome(?:/([0-9.]+))?', 1],
        ['Firefox', '(Firefox|BonEcho|GranParadiso|Lorentz|Minefield|Namoroka|Shiretoko|FxiOS)(?:[ /]([0-9.]+))?', 2],
        ['OperaNext', 'Opera.+Edition Next.+Version/([0-9.]+)', 1],
        ['OperaNext', '(?:Opera|OPR)[/ ](?:9.80.*Version/)?([0-9.]+).+Edition Next', 1],
        ['Opera', '(?:Opera|OPR)[/ ]?(?:9.80.*Version/)?([0-9.]+)', 1],
        ['Safari', 'Version/([0-9.]+).*Safari/', 1],
        ['Safari', 'Safari/([0-9.]+)?', 1],
        ['IE', 'MSIE[ /]([0-9.]+)?', 1],
        ['IE', 'Windows.*Trident/4.0', 8.0, true],
        ['IE', 'Windows.*Trident/5.0', 9.0, true],
        ['IE', 'Windows.*Trident/6.0', 10.0, true],
        ['IE', 'Windows.*Trident/7.0', 11.0, true],
    ];

    /**
     * HttpHeader 数组
     * @var array
     */
    protected $headers = [];

    /**
     * HttpHeader userAgent
     * @var ?string
     */
    protected $userAgent = null;

    /**
     * 是否有移动设备的 header
     * @var bool
     */
    protected $hasMobileHeader = null;

    /**
     * 操作系统 类型
     * @var string
     */
    protected $systemType = false;

    /**
     * 操作系统版本
     * @var string
     */
    protected $systemDetail = null;

    /**
     * 浏览器 类型
     * @var mixed
     */
    protected $browserType = false;

    /**
     * 浏览器 版本号
     * @var mixed
     */
    protected $browserVersion = false;

    /**
     * 浏览器 内核
     * @var mixed
     */
    protected $browserEngine = false;

    /**
     * 浏览器 内核版本
     * @var mixed
     */
    protected $browserEngineVersion = false;

    /**
     * spider 爬虫名称
     * @var string
     */
    protected $spiderName = false;

    /**
     * spider 爬虫厂商
     * @var string
     */
    protected $spiderVendor = false;

    /**
     * 客户端设备类型
     * @var bool
     */
    protected $deviceType = null;

    /**
     * 移动设备 名称
     * @var string
     */
    protected $deviceName = false;

    /**
     * 移动设备 品牌
     * @var string
     */
    protected $deviceBrand = false;

    /**
     * 设备匹配类
     * @var null|Object
     */
    protected $deviceClass = null;

    /**
     * 创建 Client 对象
     * 有条件的话可以将整个 Request headers 作为参数传递进来，没有条件的话仅传递 userAgent header;
     * 传入所有 header 可以提高判断准确度，比如有些 header 只在移动端才会出现，或者只在特定浏览器才会出现。
     * @param array $headers
     */
    public function __construct(array $headers = [])
    {
        $this->setHeaders($headers);
    }

    /**
     * 设置 HttpHeader
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        $this->hasMobileHeader = null;
        $this->setUserAgent($headers['USER_AGENT'] ?? null);
        return $this;
    }

    /**
     * setHeaders 别名
     * @param array $headers
     * @return Client
     */
    public function reset(array $headers)
    {
        return $this->setHeaders($headers);
    }

    /**
     * 是否包含移动访问的 Header 头
     * @return bool
     */
    protected function isMobileHeader()
    {
        if (is_bool($this->hasMobileHeader)) {
            return $this->hasMobileHeader;
        }
        foreach (static::$mobileHeaders as $key => $match) {
            if (!isset($this->headers[$key])) {
                continue;
            }
            if (!is_array($match)) {
                return $this->hasMobileHeader = true;
            }
            foreach ($match['matches'] as $find) {
                if (false !== strpos($this->headers[$key], $find)) {
                    return $this->hasMobileHeader = true;
                }
            }
        }
        return $this->hasMobileHeader = false;
    }

    /**
     * 设置 userAgent
     * @param ?string $userAgent
     * @return $this
     */
    public function setUserAgent(?string $userAgent)
    {
        if ($this->userAgent !== $userAgent = trim($userAgent)) {
            $this->userAgent = $this->headers['USER_AGENT'] = $userAgent;
            $this->systemDetail = $this->deviceType = null;
            if (empty($userAgent)) {
                $this->systemType = null;
                $this->browserType = $this->browserVersion = $this->browserEngine = $this->browserEngineVersion = null;
                $this->deviceName = $this->deviceBrand = null;
                $this->spiderVendor = $this->spiderName = null;
            } else {
                $this->systemType = false;
                $this->browserType = $this->browserVersion = $this->browserEngine = $this->browserEngineVersion = false;
                $this->deviceName = $this->deviceBrand = false;
                $this->spiderVendor = $this->spiderName = false;
            }
        }
        return $this;
    }

    /**
     * 获取 userAgent
     * @return ?string
     */
    public function userAgent()
    {
        return $this->userAgent;
    }

    /**
     * 判断 userAgent 是否存在指定字符
     * @param string $str
     * @return bool
     */
    public function find(string $str)
    {
        return false !== stripos($this->userAgent, $str);
    }

    /**
     * 判断 userAgent 是否匹配指定正则规则, 成功返回匹配结果
     * @param string $regex
     * @return array|false
     */
    public function match(string $regex)
    {
        if (null === $userAgent = $this->userAgent) {
            return false;
        }
        if (preg_match(sprintf('#%s#is', $regex), $userAgent, $matches)) {
            return $matches;
        }
        return false;
    }

    /**
     * Android 移动操作系统解析
     * @see https://developer.chrome.com/multidevice/user-agent
     * @return bool
     */
    protected function parseAndroidSystem()
    {
        if ($matches = $this->match('Android(\s([0-9.]+))?')) {
            $this->systemType = self::SYSTEM_ANDROID;
            if (isset($matches[2])) {
                $this->systemDetail = $matches[2];
            }
            if ($this->match('\/(.+?)\sMobile\b')) {
                $this->deviceType = self::DEVICE_PHONE;
            } elseif ($this->match('Archos.*GAMEPAD([2]?)') || $this->find('OUYA')) {
                // 认为是游戏主机
                // http://www.archos.com/gb/products/tablets/themed/archos_gamepad2/index.html
                // https://zh.wikipedia.org/wiki/Ouya
                $this->deviceType = self::DEVICE_CONSOLE;
            } elseif ($matches = $this->match(
                'Changhong|leTV|Kaiboer|HiMedia|10moons|Haier|SonyBDP|SonyDBV|'.
                'Panasonic MIL DLNA|PANATV|NETTV|DuneHD|Vizio|GTV100|\bTV\b'
            )) {
                // 收集一些 android Tv 的 ua
                // 因 tv 的体验与 tablet 很相似 并且大部分 tv 系统没有内置浏览器或弱化了浏览器功能  所以仅从网上收集一小部分
                $this->deviceType = self::DEVICE_TV;
                if (false !== stripos($matches[0], 'sony')) {
                    $this->deviceBrand = 'Sony';
                } elseif (false !== stripos($matches[0], 'PANATV') || false !== stripos($matches[0], 'Panasonic')) {
                    $this->deviceBrand = 'Panasonic';
                } elseif (false !== stripos($matches[0], 'NETTV')) {
                    $this->deviceBrand = 'Philips';
                } elseif ('tv' == strtolower($matches[0])) {
                    $this->deviceBrand = null;
                } else {
                    $this->deviceBrand = ucfirst(strtolower($matches[0]));
                }
                $this->deviceName = $this->deviceBrand ? $this->deviceBrand.'Tv' : null;
            } else {
                // 其他 都假设为平板
                $this->deviceType = self::DEVICE_TABLET;
            }
            /* 可能会不准确 需更多数据验证
            if ($matches = $this->match('\s([^;]+)\sBuild/(.+?)')) {
                $this->deviceName = $matches[1];
            }
            */
            return true;
        }
        return false;
    }

    /**
     * iOS 移动操作系统解析
     * @return bool
     */
    protected function parseIosSystem()
    {
        if ($matches = $this->match('.*\((.+?);.*(like Mac OS X|iOS)')) {
            $this->systemType = self::SYSTEM_IOS;
            $this->deviceBrand = 'Apple';
            $this->deviceName = $matches[1];  //etc: iphone  ipod  ipad (maybe apple Tv in future)
            $this->systemDetail = ($matches = $this->match('i?OS\s([0-9._]+)')) ? str_replace('_','.',$matches[1]) : null;
        } elseif ($this->match('Apple\s?Tv')) {
            $this->systemType = self::SYSTEM_IOS;
            $this->deviceBrand = 'Apple';
            $this->deviceName = 'AppleTv';
            $this->systemDetail = ($matches = $this->match('Tv/(.+?)\b')) ? str_replace('_','.',$matches[1]) : null;
        }
        if (false !== $this->systemType) {
            $this->deviceType = false !== stripos($this->deviceName, 'tv') ? self::DEVICE_TV :
                (false !== stripos($this->deviceName, 'ipad') ? self::DEVICE_TABLET : self::DEVICE_PHONE);
            return true;
        }
        return false;
    }

    /**
     * Windows Phone(Mobile) 移动操作系统解析
     * @return bool
     */
    protected function parseWindowsMobileSystem()
    {
        if ($matches = $this->match('Windows Phone (?:OS)?[ ]?(\d+[\.\d]+)|Windows NT ([0-9.]+); ARM;|XBLWP7|ZuneWP7')) {
            $this->systemType = self::SYSTEM_WINDOWS_PHONE;
            if (isset($matches[2])) {
                if ('6.4' == $matches[2]) {
                    $this->systemDetail = 10;
                } elseif ('6.3' == $matches[2]) {
                    $this->systemDetail = 8.1;
                } elseif ('6.2' == $matches[2]) {
                    $this->systemDetail = 8;
                } else {
                    $this->systemDetail = $matches[2];
                }
            } elseif (isset($matches[1])) {
                $this->systemDetail = $matches[1];
            } elseif (in_array(strtolower($matches[0]), ['xblwp7','zunewp7'])) {
                $this->systemDetail = 7;
            }
            if ($this->systemDetail >= 10 || '8' == $this->systemDetail || '8.1' == $this->systemDetail) {
                $this->deviceType = $this->match('Tablet|Touch') ? self::DEVICE_TABLET : self::DEVICE_PHONE;
            } else {
                $this->deviceType = self::DEVICE_PHONE;
            }
        } elseif ($this->match('Windows CE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Windows CE|Window Mobile|WCE;')) {
            $this->systemType = self::SYSTEM_WINDOWS_MOBILE;
            $this->systemDetail = null;
            // https://zh.wikipedia.org/wiki/Dreamcast
            $this->deviceType = $this->find('Dreamcast') ? self::DEVICE_CONSOLE : self::DEVICE_PHONE;
        } elseif ($this->match('windows.*Mobile')) {
            $this->systemType = self::SYSTEM_WINDOWS_PHONE;
            $this->systemDetail = null;
            $this->deviceType = self::DEVICE_PHONE;
        }
        if (false !== $this->systemType) {
            if (false !== stripos($this->userAgent,'Xbox')) {
                $this->deviceType = self::DEVICE_CONSOLE;
                $this->deviceBrand = 'Microsoft';
                if ($matches = $this->match('Xbox\s[a-z0-9]+')) {
                    $this->deviceName = $matches[0];
                } else {
                    $this->deviceName = 'Xbox';
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 其他 非桌面操作系统检测
     * @see https://en.wikipedia.org/wiki/Mobile_operating_system
     * @return bool
     */
    protected function parseMobileSystem()
    {
        // BlackBerry
        if (
            is_array($matches = $this->match('(?:BB10;.+Version|Black[Bb]erry[0-9a-z]+|Black[Bb]erry.+Version)/(\d+[\.\d]+)')) ||
            $matches = $this->find('BlackBerry')
        ) {
            $this->systemType = self::SYSTEM_BLACKBERRY;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = $matches[1] ?? null;
            $this->deviceBrand = 'BlackBerry';
            $this->deviceName = 'BlackBerry';
        }
        // PlayBook
        elseif (
            is_array($matches = $this->match('RIM Tablet OS(\s([0-9.]+))?')) ||
            is_array($matches = $this->match('RIM Tablet OS|QNX|Play[Bb]ook'))
        ) {
            $this->systemType = self::SYSTEM_PLAYBOOK;
            $this->deviceType = self::DEVICE_TABLET;
            $this->systemDetail = $matches[2] ?? null;
            $this->deviceBrand = 'BlackBerry';
            $this->deviceName = 'PlayBook';
        }
        // Tizen
        elseif ($matches = $this->match('Tizen[ /]?(\d+[\.\d]+)?')) {
            $this->systemType = self::SYSTEM_TIZEN;
            $this->systemDetail = $matches[1] ?? null;
            // http://developer.samsung.com/technical-doc/view.do?v=T000000203
            $this->deviceType = $this->match('(SmartTv|Smart-TV)') ? self::DEVICE_TV : self::DEVICE_PHONE;
            $this->deviceBrand = 'Samsung';
        }
        // Symbian
        elseif ($matches = $this->match('Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b')) {
            $this->systemType = self::SYSTEM_SYMBIAN;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = 'Nokia';
            $this->deviceName = ($matches = $this->match('Nokia(\s|;|-|_)?([a-z0-9-]+)')) ? $matches[2] : 'Nokia';
        }
        // Sailfish
        elseif ($matches = $this->match('Sailfish|Jolla')) {
            $this->systemType = self::SYSTEM_SAILFISH;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = 'Jolla';
            $this->deviceName = 'Jolla';
        }
        // MeeGo Maemo
        elseif ($matches = $this->match('MeeGo|WeTab')) {
            $this->systemType = self::SYSTEM_MEEGO;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = 'Nokia';
            $this->deviceName = 'N9';
        } elseif ($matches = $this->find('Maemo')) {
            $this->systemType = self::SYSTEM_MEAGO;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = 'Nokia';
            $this->deviceName = ($matches = $this->match('(700|800|810|900)')) ? 'N'.$matches[0] : 'Nokia';
        }
        // Palm
        elseif ($matches = $this->match('PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|webOS')) {
            $this->systemType = self::SYSTEM_PALM;
            $this->deviceType = $this->find('touch') ? self::DEVICE_TABLET : self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = 'HP';
            $this->deviceName = 'Palm';
        }
        // Brew
        elseif ($matches = $this->match('(Brew MP|BREW|BMP)(\s|/)?([a-z0-9-]+)')) {
            $this->systemType = self::SYSTEM_BREW;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = $matches[3] ?? null;
            $this->deviceBrand = null;
            $this->deviceName = null;
        }
        // Bada
        elseif ($matches = $this->match('bada(?:[ /](\d+[\.\d]+))')) {
            $this->systemType = self::SYSTEM_BADA;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = $matches[1] ?? null;
            $this->deviceBrand = 'Samsung';
            $this->deviceName = ($matches = $this->match('SAMSUNG-([a-z0-9-]+)(\s|/|;)')) ? $matches[1] : null;
        }
        // Java
        elseif ($matches = $this->match('J2ME/|\bMIDP\b|\bCLDC\b')) {
            $this->systemType = self::SYSTEM_JAVA;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = null;
            $this->deviceName = null;
        }
        // GoogleTv HbbTv WebTv
        elseif (is_array($matches = $this->match('(GoogleTV|HbbTv|WebTv|SmartTv|Smart-Tv)(?:[ /](\d+[\.\d]+))?'))) {
            $this->deviceType = self::DEVICE_TV;
            $this->systemDetail = $matches[2] ?? null;
            $matches[1] = strtolower($matches[1]);
            if ('googletv' == $matches[1]) {
                $this->systemType = self::SYSTEM_GOOGLETV;
            } elseif ('hbbtv' == $matches[1]) {
                $this->systemType = self::SYSTEM_HBBTV;
            } elseif ('webtv' == $matches[1]) {
                $this->systemType = self::SYSTEM_WEBTV;
            } elseif (false !== strpos($matches[1], 'smart')) {
                $this->systemType = 'Tizen';
                $this->deviceBrand = 'Samsung';
            }
        }
        // Nintendo
        elseif (is_array($matches = $this->match('(New\s)?Nintendo\s(WiiU?|3?DS)?'))) {
            $this->systemType = self::SYSTEM_NINTENDO;
            $this->deviceType = self::DEVICE_CONSOLE;
            $this->systemDetail = null;
            $this->deviceBrand = 'Nintendo';
            $this->deviceName = isset($matches[0]) ? trim($matches[0]) : null;
        }
        // PlayStation
        elseif (is_array($matches = $this->match('PlayStation ((?:Portable|Vita))'))) {
            $this->systemType = self::SYSTEM_PLAYSTATION;
            $this->deviceType = self::DEVICE_CONSOLE;
            $this->systemDetail = $matches[1] ?? null;
            $this->deviceBrand = 'Sony';
            $this->deviceName = 'PS4';
        }
        // Firefox OS
        elseif ($matches = $this->match('(TV|Mobile|Tablet);.+Firefox/(\d+\.\d+)')) {
            // https://developer.mozilla.org/en-US/docs/Web/HTTP/Gecko_user_agent_string_reference
            $this->systemType = self::SYSTEM_FIREFOXOS;
            $this->systemDetail = $matches[2];
            $matches[1] = strtolower($matches[1]);
            if ('tv' == $matches[1]) {
                $this->deviceType = self::DEVICE_TV;
            } elseif ('tablet' == $matches[1]) {
                $this->deviceType = self::DEVICE_TABLET;
            } else {
                $this->deviceType = self::DEVICE_PHONE;
            }
        }
        // 最后再检测一次 apple 家族的
        elseif ($matches = $this->match('iPhone|iPad|iPod')) {
            $this->systemType = self::SYSTEM_IOS;
            $this->deviceBrand = 'Apple';
            $this->deviceName = $matches[0];
            $this->systemDetail = null;
        }
        if (false !== $this->systemType) {
            return true;
        }
        return false;
    }

    /**
     * Windows 桌面操作系统检测
     * @see https://zh.wikipedia.org/wiki/Windows_NT
     * @see https://zh.wikipedia.org/wiki/Uname
     * @see http://stackoverflow.com/questions/18070154/get-operating-system-info-with-php
     * @return bool
     */
    protected function parseWindowSystem()
    {
        if ($matches = $this->match('(YGWIN_NT|windows nt|Windows Server)((\s|-)([a-z0-9.]+)(\s|;)?)?')) {
            $this->systemType = self::SYSTEM_WINDOWS;
            if (isset($matches[4])) {
                $windowsVersion = [
                    '6.4' => '10',
                    '6.3' => '8.1',
                    '6.2' => '8',
                    '6.1' => '7',
                    '6.0' => 'Vista',
                    '5.1' => 'XP',
                    '5.0' => '2000',
                    '4.0' => 'NT'
                ];
                $this->systemDetail = $windowsVersion[$matches[4]] ?? 'NT';
            }
            if (stripos($matches[0], 'server')) {
                $this->systemDetail = 'Server' . (isset($matches[4]) ? ' '.$matches[4] : '');
            }
        } elseif ($matches = $this->match('windows\s([a-z0-9.]+)')) {
            $this->systemType = self::SYSTEM_WINDOWS;
            $this->systemDetail = trim($matches[1]);
        } elseif ($matches = $this->match('Windows|CYGWIN_98|Win98|CYGWIN_95|Win95|Win32|CYGWIN_ME|Win9x|Win 9x')) {
            $this->systemType = self::SYSTEM_WINDOWS;
            if (strtolower($matches[0]) != 'windows') {
                $this->systemDetail =
                strpos($matches[0], '98') ? '98' : (strpos($matches[0], '95') || strpos($matches[0], '32') ? '95' : 'ME');
            }
        }
        if (false !== $this->systemType) {
            if ($this->systemDetail >= 10 || '8' == $this->systemDetail || '8.1' == $this->systemDetail) {
                $this->deviceType = $this->match('Tablet|Touch') ? self::DEVICE_TABLET : self::DEVICE_DESKTOP;
            } else {
                $this->deviceType = self::DEVICE_DESKTOP;
            }
            return true;
        }
        return false;
    }

    /**
     * Mac 桌面操作系统检测
     * @return bool
     */
    protected function parseMacSystem()
    {
        if ($this->match('Macintosh|Mac OS X|Mac_PowerPC|Mac PowerPC|Darwin')) {
            $this->systemType = self::SYSTEM_MACINTOSH;
            $this->deviceType = self::DEVICE_DESKTOP;
            if ($matches = $this->match('(Mac OS X|PowerPC)\s([0-9_]+)')) {
                $this->systemDetail = (string) str_replace('_','.',$matches[2]);
            }
            return true;
        }
        return false;
    }

    /**
     * unix 桌面操作系统检测
     * @return bool
     */
    protected function parseUnixSystem()
    {
        if (false === $this->systemType) {
            if ($matches = $this->match('Ubuntu|debian|OpenBSD|CrOS|NetBSD|SunOs|Solaris|DragonFly|Syllable|IRIX|IRIX')) {
                $this->systemType = self::SYSTEM_UNIX;
                $this->systemDetail = $matches[0];
            } elseif ($matches = $this->match('X11;\s?([U|N|I];)?(.+?)(\s|;)')) {
                $this->systemType = self::SYSTEM_UNIX;
                $this->systemDetail = trim($matches[2]);
            } elseif ($this->match('Linux')) {
                $this->systemType = self::SYSTEM_UNIX;
                $this->systemDetail = 'Linux';
            }
        }
        if (false !== $this->systemType) {
            $this->deviceType =  self::DEVICE_DESKTOP;
            // http://cn.ubuntu.com/phone/
            if ('Ubuntu' == $this->systemDetail) {
                if (($tablet = $this->find('Tablet')) || $this->find('Mobile')) {
                    $this->deviceType = $tablet ? self::DEVICE_TABLET : self::DEVICE_PHONE;
                    if ($matches = $this->match('mx|meizu')) {
                        $this->deviceBrand = 'MEIZU';
                        $this->deviceName = $matches[0];
                    } elseif ($this->find('Aquaris')) {
                        $this->deviceBrand = 'BQ';
                        $this->deviceName = 'Aquaris';
                    }
                }
            } elseif ($this->find('NetCast.TV')) {
                // https://developer.lge.com/webOSTV/develop/web-app/webos-tv-platform/web-engine/
                $this->deviceType = self::DEVICE_TV;
                $this->deviceBrand = 'LG';
                $this->deviceName = 'LG SmartTv';
            } elseif ($this->find('Kindle')) {
                $this->deviceType = self::DEVICE_TABLET;
                $this->deviceBrand = 'Amazon';
                $this->deviceName = 'Kindle';
            }
            return true;
        }
        return false;
    }

    /**
     * wap 时代功能机检测
     * @see http://detectmobilebrowsers.com/
     * @see http://www.webcab.de/wapua.htm
     * @return bool
     */
    protected function parseWapSystem()
    {
        if (preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s-)|ai(ko|rn)|al(av|ca|co)|'.
            'amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|'.
            'br[ev]w|bumb|bw-[nu]|c55\/|capi|ccwa|cdm-|cell|chtm|cldc|cmd-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc-s|'.
            'devi|dica|dmob|do[cp]o|ds(12|-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly[-_]|'.
            'g1 u|g560|gene|gf-5|g-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd-[mpt]|hei-|hi(pt|ta)|hp( i|ip)|hs-c|'.
            'ht(c[ -_agpst]|tp)|hu(aw|tc)|i-(20|go|ma)|i230|iac[ -\/]|ibro|idea|ig01|ikom|im1k|inno|ipaq|'.
            'iris|ja[tv]a|jbro|jemu|jigs|kddi|keji|kgt[ \/]|klon|kpt |kwc-|kyo[ck]|le(no|xi)|lg( g|\/[klu]|50|54|'.
            '-[a-w])|libw|lynx|m1-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|'.
            'mo(01|02|bi|de|do|t[ -ov]|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30[02]|n50[025]|'.
            'n7(0[01]|10)|ne([cm]-|on|tf|wf|wg|wt)|nok[6i]|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan[adt]|pdxg|'.
            'pg(13|-([1-8]|c))|phil|pire|pl(ay|uc)|pn-2|po(ck|rt|se)|prox|psio|pt-g|qa-a|'.
            'qc(07|12|21|32|60|-[2-7]|i-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|'.
            'sc(01|h-|oo|p-)|sdk\/|se(c[-01]|47|mc|nd|ri)|sgh-|shar|sie[-m]|sk-0|sl(45|id)|sm(al|ar|b3|it|t5)|'.
            'so(ft|ny)|sp(01|h-|v-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl-|tdg-|tel[im]|tim-|t-mo|'.
            'to(pl|sh)|ts(70|m-|m3|m5)|tx-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|-v)|vm40|voda|'.
            'vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c[- ]|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas-|your|zeto|'.
            'zte-/i', substr($this->userAgent, 0, 4))) {
            $this->systemType = self::SYSTEM_JAVA;
            $this->deviceType = self::DEVICE_PHONE;
            $this->systemDetail = null;
            $this->deviceBrand = null;
            $this->deviceName = null;
            return true;
        }
        return false;
    }

    /**
     * 获取 操作系统类型
     * @return ?string
     */
    public function system()
    {
        if (false !== $this->systemType) {
            return $this->systemType;
        }
        // 按照市场份额(先移动后桌面)顺序进行解析 可能尝试次数越少 命中率(效率)较高
        if ($this->parseAndroidSystem() ||
            $this->parseIosSystem() ||
            $this->parseWindowsMobileSystem() ||
            $this->parseMobileSystem() ||
            $this->parseWindowSystem() ||
            $this->parseMacSystem() ||
            $this->parseUnixSystem() ||
            $this->parseWapSystem()
        ) {
            return $this->systemType;
        }
        // 是否为移动端 依赖此检测 所以做一个最后尝试
        $this->deviceType = $this->match('\b(Mobile|Tablet|Touch|Tv)\b') ? self::DEVICE_PHONE : self::DEVICE_DESKTOP;
        return $this->systemType = null;
    }

    /**
     * 操作系统细节 (比如: mac android 返回版本  unix windows 返回发行版名称)
     * @return ?string
     */
    public function systemDetail()
    {
        if (null === $this->system()) {
            $this->systemDetail = null;
        }
        return $this->systemDetail;
    }

    /**
     * 操作系统名称或细节中是否包含 $match 关键词 ( ex: Client->isSystem('xp') )
     * @param string $match
     * @return bool
     */
    public function isSystem(string $match)
    {
        if (null === $this->system()) {
            return false;
        }
        return stripos($this->systemType, $match) !== false || stripos($this->systemDetail, $match) !== false;
    }

    /**
     * 是否移动设备访问 (所有非 desktop 设备)
     * @return bool
     */
    public function isMobile()
    {
        // 仅关心是否为移动设备 可以先使用速度最快的方式(检测 Header 头)
        if ($this->isMobileHeader()) {
            return true;
        }
        if (null !== $this->system()) {
            return $this->deviceType !== self::DEVICE_DESKTOP;
        }
        return false;
    }

    /**
     * 判断是否为指定类型
     * @param int $device
     * @return bool
     */
    protected function isDeviceType(int $device = self::DEVICE_MOBILE)
    {
        $system = $this->system();
        if (null === $system || $this->deviceType === self::DEVICE_DESKTOP) {
            return false;
        }
        return $this->deviceType === $device;
    }

    /**
     * 是否手机访问
     * @return bool
     */
    public function isPhone()
    {
        return $this->isDeviceType(self::DEVICE_PHONE);
    }

    /**
     * 是否 平板 访问
     * @return bool
     */
    public function isTablet()
    {
        return $this->isDeviceType(self::DEVICE_TABLET);
    }

    /**
     * 是否 智能TV 访问
     * @return bool
     */
    public function isTv()
    {
        return $this->isDeviceType(self::DEVICE_TV);
    }

    /**
     * 是否 游戏主机 访问
     * @return bool
     */
    public function isConsole()
    {
        return $this->isDeviceType(self::DEVICE_CONSOLE);
    }

    /**
     * 获取 爬虫名称
     * @return ?string
     */
    public function spider()
    {
        if (false !== $this->spiderName) {
            return $this->spiderName;
        }
        foreach (static::$robotSpider as $robot => $regex) {
            if ($matches = $this->match($regex)) {
                $this->spiderVendor = $robot;
                $this->spiderName = $matches[0];
                return $this->spiderName;
            }
        }
        if (false === $this->spiderVendor && $this->match('http://[^\s]') ) {
            if ($matches = $this->match('([0-9a-z_-]+)(Bot|Spider|Crawler)')) {
                $this->spiderVendor = $matches[1];
                $this->spiderName = $matches[0];
                return $this->spiderName;
            }
        }
        return $this->spiderName = $this->spiderVendor = null;
    }

    /**
     * 获取 爬虫厂商
     * @return ?string
     */
    public function spiderVendor()
    {
        if (false !== $this->spiderVendor) {
            return $this->spiderVendor;
        }
        if (null === $this->spider()) {
            return null;
        }
        return $this->spiderVendor;
    }

    /**
     * 爬虫或爬虫厂商 是否包含 $match 关键词 ( ex: Client->isRobot('google') )
     * @param string $match
     * @return bool
     */
    public function isSpider(string $match)
    {
        if (null === $this->spider()) {
            return false;
        }
        return false !== stripos($this->spiderName, $match) || false !== stripos($this->spiderVendor, $match);
    }

    /**
     * iOS 尤其一些 app 内置 webView 返回的版本号为 webkit 的, 认为是 safari 且转为对应版本
     * @see https://en.wikipedia.org/wiki/Safari_version_history
     * @param string $version
     * @return string
     */
    protected function getSafariVersionByWebkit(string $version)
    {
        $versions = explode('.', $version);
        $version = intval($versions[0]);
        if (isset($versions[1])) {
            $version += 0.1 * intval($versions[1]);
        }
        if ($this->isMobile()) {
            $compare = [
                '601.5' => '9.1',
                '601.2' => '9.0',
                '538.35' => '8.0',
                '537.79' => '7.1',
                '537.71' => '7.0',
                '536.25' => '6.0',
                '533.16' => '5.0',
                '526.11' => '4.0',
                '522.11' => '3.0',
                '412' => '2.0',
            ];
        } else {
            $compare = [
                '534.50' => '5.1',
                '533.16' => '5.0',
                '526.12' => '4.0',
                '525.26' => '3.2',
                '525.13' => '3.1',
                '522.11' => '3.0',
            ];
        }
        foreach ($compare as $k => $v) {
            if ($version >= $k) {
                return $v;
            }
        }
        return current($compare);
    }

    /**
     * 获得 浏览器类型
     * @return string|null
     */
    public function browser()
    {
        if (false !== $this->browserType) {
            return $this->browserType;
        }
        $browsers = $this->isMobile() ? static::$mobileBrowsers : static::$desktopBrowsers;
        foreach ($browsers as $browser) {
            if ($matches = $this->match($browser[1])) {
                if (isset($browser[3]) && $browser[3]) {
                    $this->browserType = $browser[0];
                    $this->browserVersion = $browser[2];
                } elseif ('Generic' === $browser[0]) {
                    $this->browserType = str_ireplace('browser', '', $matches[1]);
                    $this->browserVersion = $matches[3] ?? null;
                } else {
                    $this->browserType = $browser[0];
                    $this->browserVersion = $matches[$browser[2]] ?? null;
                }
                break;
            }
        }
        if (false === $this->browserType) {
            if ($this->browserEngine() === self::BROWSER_WEBKIT) {
                $this->browserType = 'Safari';
                $this->browserVersion = $this->browserEngineVersion;
            }
        }
        if (false !== $this->browserType) {
            if ('Safari' == $this->browserType && null !== $this->browserVersion &&
                static::getFormatVersion($this->browserVersion) > 100) {
                $this->browserVersion = $this->getSafariVersionByWebkit($this->browserVersion);
            }
            return $this->browserType;
        }
        return $this->browserType = $this->browserVersion = null;
    }

    /**
     * 获得 浏览器版本
     * @param bool $full
     * @return string|int
     */
    public function browserVersion(bool $full = false)
    {
        if (false !== $this->browserVersion || null !== $this->browser()) {
            return static::getFormatVersion($this->browserVersion, $full);
        }
        return null;
    }

    /**
     * 获得浏览器引擎
     * @return string|null
     */
    public function browserEngine()
    {
        if (false !== $this->browserEngine) {
            return $this->browserEngine;
        }
        foreach (static::$browserEngines as $browser) {
            if ($matches = $this->match($browser[1])) {
                $this->browserEngine = $browser[0];
                $this->browserEngineVersion = $matches[$browser[2]] ?? null;
                return $this->browserEngine;
            }
        }
        return $this->browserEngine = $this->browserEngineVersion = null;
    }

    /**
     * 获得 浏览器引擎版本
     * @param bool $full
     * @return string|int
     */
    public function browserEngineVersion(bool $full = false)
    {
        if (false !== $this->browserEngineVersion || null !== $this->browserEngine()) {
            return static::getFormatVersion($this->browserEngineVersion, $full);
        }
        return null;
    }

    /**
     * 浏览器是否包含 $match 关键词 ( ex: Client->isRobot('chrome') )
     * @param string $match
     * @return bool
     */
    public function isBrowser(string $match)
    {
        if (null === $this->browser()) {
            return false;
        }
        return false !== stripos($this->browserType, $match) || false !== stripos($this->browserEngine, $match);
    }

    /**
     * 是否 微信内置浏览器
     * @return bool
     */
    public function isWeChat()
    {
        return $this->find('MicroMessenger');
    }

    /**
     * 是否 QQ内置浏览器
     * @return bool
     */
    public function isQQ()
    {
        return (bool) $this->match('QQ[ /](\d+[\.\d]+)?');
    }

    /**
     * 是否 新浪微博内置浏览器
     * @return bool
     */
    public function isWeiBo()
    {
        return $this->find('WeiBo__');
    }

    /**
     * 是否 Wifi 访问 (部分 app 内置浏览器(如:QQ,微信) ua 有此信息 缺省返回 true)
     * @return bool
     */
    public function isWifi()
    {
        // http://www.uc.cn/download/UCBrowser_User_Agent.pdf
        $ua = $this->headers['X-UCBrowser-UA'] ?? $this->userAgent;
        return false === stripos($ua, 'NetType') ||
            false !== stripos($ua, 'NetType/Net') || false !== stripos($ua, 'NetType/Wifi');
    }

    /**
     * 获取客户端访问机的 品牌 设备
     * @param bool $getBrand
     * @return string
     */
    protected function parseMobileDevice(bool $getBrand = false)
    {
        if (null !== $this->system()) {
            if ($getBrand && false !== $this->deviceBrand) {
                return $this->deviceBrand;
            } elseif (!$getBrand && false !== $this->deviceName) {
                return $this->deviceName;
            }
        }
        // 根据收集的信息 进行匹配
        if (null === $this->deviceClass) {
            $this->deviceClass = new Device();
        }
        $devices = $this->deviceClass->get($this->userAgent);
        $this->deviceBrand = $devices[0];
        $this->deviceName = $devices[1];
        return $getBrand ? $this->deviceBrand : $this->deviceName;
    }

    /**
     * 移动智能设备 机型
     * @return string
     */
    public function device()
    {
        if (false !== $this->deviceName) {
            return $this->deviceName;
        }
        return $this->parseMobileDevice();
    }

    /**
     * 移动智能设备品牌
     * @return string
     */
    public function brand()
    {
        if (false !== $this->deviceBrand) {
            return $this->deviceBrand;
        }
        return $this->parseMobileDevice(true);
    }


    /**
     * 格式化版本号
     * @param ?string $version
     * @param bool $full
     * @return string|int
     */
    protected static function getFormatVersion(?string $version, bool $full = false)
    {
        if (null === $version) {
            return null;
        }
        if ($full) {
            return $version;
        }
        $versions = explode('.', $version);
        return intval($versions[0]);
    }
}

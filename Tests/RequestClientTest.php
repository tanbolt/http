<?php

use PHPUnit\Framework\TestCase;
use Tanbolt\Http\Request\Client;

class RequestClientTest extends TestCase
{
    /**
     * @dataProvider getUserAgentList
     * @param $userAgent
     * @param $system
     * @param $systemDetail
     * @param $isMobile
     * @param $browser
     * @param $browserVersion
     * @param $browserEngine
     * @param $browserEngineVersion
     * @param $spiderVendor
     * @param $spider
     * @param $device
     * @param $brand
     */
    public function testClient(
        $userAgent, $system, $systemDetail, $isMobile,
        $browser, $browserVersion, $browserEngine, $browserEngineVersion,
        $spiderVendor, $spider, $device, $brand
    ) {
        $client = new Client();
        $client->setUserAgent($userAgent);

        static::assertEquals($system, $client->system());
        static::assertEquals($systemDetail, $client->systemDetail());
        static::assertEquals($isMobile, $client->isMobile());
        static::assertEquals($browser, $client->browser());
        static::assertEquals($browserVersion, $client->browserVersion());
        static::assertEquals($browserEngine, $client->browserEngine());
        static::assertEquals($browserEngineVersion, $client->browserEngineVersion());
        static::assertEquals($spider, $client->spider());
        static::assertEquals($spiderVendor, $client->spiderVendor());
        static::assertEquals($device, $client->device());
        static::assertEquals($brand, $client->brand());
    }

    public function getUserAgentList()
    {
        return include __DIR__.'/Fixtures/UserAgentList.php';
    }

    public function testClientSpecial()
    {
        $ios_wechat = 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_3 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13E233 MicroMessenger/6.3.15 NetType/WIFI Language/zh_CN';
        $client = (new Client())->setUserAgent($ios_wechat);
        static::assertTrue($client->find('MicroMessenger'));
        static::assertFalse($client->find('QQ'));
        static::assertEquals(['Language/zh_CN',  'zh_CN'], $client->match('Language\/(.+?)\b'));
        static::assertFalse($client->match('Language\/(.+?)\s'));

        static::assertTrue($client->isSystem('ios'));
        static::assertTrue($client->isBrowser('safari'));

        static::assertTrue($client->isPhone());
        static::assertFalse($client->isTablet());
        static::assertFalse($client->isTv());
        static::assertFalse($client->isConsole());

        static::assertTrue($client->isWifi());
        static::assertTrue($client->isWeChat());
        static::assertFalse($client->isWeiBo());
        static::assertFalse($client->isQQ());

        $client = (new Client())->setUserAgent(
            'Mozilla/5.0 (Linux; U; Android 5.0.2; zh-cn; X900 Build/CBXCNOP5500912251S) AppleWebKit/533.1 (KHTML, like Gecko)Version/4.0 MQQBrowser/5.4 TBS/025489 Mobile Safari/533.1 V1_AND_SQ_6.0.0_300_YYB_D QQ/6.0.0.2605 NetType/WIFI WebP/0.3.0 Pixel/1440'
        );
        static::assertTrue($client->isQQ());
        static::assertTrue($client->isWifi());
    }
}

<?php
namespace Tanbolt\Http\Request;

class Device
{
    /**
     * @param $ua
     * @return array
     * TODO 返回设备的品牌 型号
     */
    public function get($ua)
    {
        //return ['brand', 'device'];
        return [null, null];
    }
}

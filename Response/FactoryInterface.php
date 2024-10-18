<?php
namespace Tanbolt\Http\Response;

use Tanbolt\Http\Request;
use Tanbolt\Http\Response;

interface FactoryInterface extends \Iterator
{
    /**
     * 重置对象为初始状态
     * @return $this
     */
    public function reset();

    /**
     * 准备输出，可在该方法内配置 Response. 注意：要经得起多次调用而不产生副作用
     * @param Response $response
     * @param Request|null $request
     * @return mixed
     */
    public function prepare(Response $response, Request $request = null);
}

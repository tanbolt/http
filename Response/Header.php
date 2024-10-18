<?php
namespace Tanbolt\Http\Response;

use Tanbolt\Http\Request;
use Tanbolt\Http\Response;
use Tanbolt\Http\Request\Header as RequestHeader;

class Header extends RequestHeader
{
    public function getSent(Response $response, Request $request = null)
    {
        // 根据协议处理一些 header 头, 为了不对当前对象造成污染, 这里复制一个对象
        $header = clone $this;

        // Response Content
        // https://tools.ietf.org/html/rfc2616#section-4.4
        // https://httpwg.org/specs/rfc7230.html#header.content-length
        if ($response->isInformational() || $response->isEmpty() ||
            ($request && $response->isSuccessful() && $request->isMethod('CONNECT'))
        ) {
            $header->remove(['Content-Length']);
        } else {
            if ($header->has('Transfer-Encoding')) {
                $header->remove('Content-Length');
            }
            $charset = $response->charset();
            if ($header->has('Content-Type')) {
                $contentType = $header->getLast('Content-Type');
                if (0 === stripos($contentType, 'text/') && false === stripos($contentType, 'charset')) {
                    $header->set('Content-Type', $contentType.'; charset='.$charset);
                }
            } else {
                $header->set('Content-Type', 'text/html; charset='.$charset);
            }
        }

        //  Location
        if ($redirectUrl = $header->getLast('Location')) {
            if ($request && -1 === $redirectUrl) {
                $redirectUrl = $request->header->getLast('referer');
            }
            if (empty($redirectUrl)) {
                $redirectUrl = $request->root();
            } elseif (!preg_match('/^([0-9a-zA-z]+):\/\//', $redirectUrl)) {
                // 可能是打开 QQ tencent:// 或者手机端呼出 app, 所以 xxx:// 开头统一认为是绝对 url, 不做处理
                $redirectUrl = ('/' === $redirectUrl[0] ? $request->baseUrl() : $request->fullPath().'/') . $redirectUrl;
                $redirectUrl = $request->httpHost().static::formatUrl($redirectUrl);
            }
            $header->set('Location', $redirectUrl);
        }

        // IE8 以下浏览器 SSL 类型移除 Header cache-control 参数
        // https://support.microsoft.com/zh-cn/kb/323308
        if ($request && $request->isHttps()
            && false !== stripos($header->getLast('Content-Disposition'), 'attachment')
            && preg_match('/MSIE (.*?);/i', $request->server->get('HTTP_USER_AGENT'), $match) == 1
            && (int) preg_replace('/(MSIE )(.*?);/', '$2', $match[0]) < 9
        ) {
            $header->remove('Cache-Control');
        } else {
            // 设置默认 Cache-Control
            if (!$header->has('Cache-Control')) {
                if ($header->has(['ETag', 'Last-Modified', 'Expires'], true)) {
                    $header->setCacheControl('private')->setCacheControl('must-revalidate');
                } else {
                    $header->setCacheControl('no-cache');
                }
            }
            // 若 Cache-Control 不缓存, 针对 http/1.0 设置 Pragma / Expires
            if (1 == $response->protocolVersion() && $header->hasCacheControl(['no-store', 'no-cache'], true)) {
                $header->set('Pragma', 'no-cache')->set('Expires', '-1');
            }
        }

        // http/2 header 需要移除不支持的, 待检测;
        // https://httpwg.org/specs/rfc7540.html#rfc.section.8.1.2.2
        if ($request && 2 == $request->protocolVersion()) {
            $header->remove(['Connection', 'Proxy-Connection', 'Upgrade', 'Transfer-Encoding']);
        }
        return $header->all();
    }

    /**
     * 格式化 url
     * @param $path
     * @return string
     */
    protected static function formatUrl($path)
    {
        if (!$path) {
            return '';
        }
        $last = '';
        $path = str_replace('\\','/', $path);
        while($path != $last){
            $last = $path;
            $path = preg_replace('/\/[^\/]+\/\.\.\//', '/', $path);
        }
        $last = '';
        while($path != $last){
            $last = $path;
            $path = preg_replace('/([.\/]\/)+/', '/', $path);
        }
        return $path;
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: guoyexuan
 * Date: 2017/11/28
 * Time: 下午2:04
 */

class WebServer
{

    public function __construct()
    {

    }

    public function decode($info)
    {
        global $_SERVER;
        list($header,) = explode("\r\n\r\n",$info);
        //将请求头变为数组
        $header = explode("\r\n",$header);
        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $header[0]);
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    }
    //http协议加密
    public function encode($str)
    {
        $content = "HTTP/1.1 200 OK\r\nServer: PWS/1.0.0\r\nContent-Type: text/html;charset=utf-8\r\nContent-Length: " . strlen($str   )."\r\n\r\n{$str}";
        return $content;
    }
}
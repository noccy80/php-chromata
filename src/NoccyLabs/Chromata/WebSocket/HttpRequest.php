<?php

namespace NoccyLabs\Chromata\WebSocket;



class HttpRequest
{
    protected $headers = [];
    protected $method;
    protected $uri;
    protected $protocol;

    public function __construct($raw)
    {
        // ugh, this shouldn't be needed. probably a handy function for detecting it
        $sep = (strpos($raw,"\r\n")!==false)?"\r\n":((strpos($raw,"\n")!==false)?"\n":"\r");

        $lines = explode($sep,$raw);
        $head = array_shift($lines);
        $this->parseRequestHeader($head);

        while (count($lines)>0) {
            $header = array_shift($lines);
            if (trim($header))
                $this->parseHeaderString($header);
        }
    }

    private function parseRequestHeader($raw)
    {
        list($method, $uri, $proto) = explode(" ",$raw,3);
        $this->method = $method;
        $this->uri = $uri;
        $this->proto = $proto;
    }

    private function parseHeaderString($raw)
    {
        list($key,$value) = explode(":",$raw,2);
        $value = trim($value);
        $key = strtolower(trim($key));
        if (array_key_exists($key,$this->headers)) {
            if (is_array($this->headers[$key])) {
                $this->headers[$key][] = $value;
            } else {
                $this->headers[$key] = [$this->headers[$key]];
            }
        } else {
            $this->headers[$key] = $value;
        }
    }

    public function getHeader($key,$all=false)
    {
        if (!array_key_exists($key,$this->headers)) {
            return null;
        }
        if (is_array($this->headers[$key])) {
            if ($all) {
                return $this->headers[$key];
            } else {
                return reset($this->headers[$key]);
            }
        }
        return $this->headers[$key];
    }
}

/*

GET / HTTP/1.1
Host: 127.0.0.1:9101
Connection: Upgrade
Pragma: no-cache
Cache-Control: no-cache
Upgrade: websocket
Origin: http://127.0.0.1:9100
Sec-WebSocket-Version: 13
User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36
DNT: 1
Accept-Encoding: gzip, deflate, sdch, br
Accept-Language: en-US,en;q=0.8,sv;q=0.6
Cookie: PHPSESSID=6dfh2q3qi48n1qt56ecoanqvr7
Sec-WebSocket-Key: HyD86/77ojM0OKwBPxW2OQ==
Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits

*/

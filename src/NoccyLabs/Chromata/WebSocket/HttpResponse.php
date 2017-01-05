<?php

namespace NoccyLabs\Chromata\WebSocket;



class HttpResponse
{
    protected $status;
    protected $uri;
    protected $protocol;
    protected $headers = [];
    protected $status_str = [
        101 => "Switching protocols",
        200 => "Content follows"
    ];
    protected $body;

    public function __construct($status, $uri, $protocol)
    {
        $this->status = $status;
        $this->uri = $uri;
        $this->protocol = $protocol;
    }

    public function __toString()
    {
        $head = sprintf("%s %d %s\r\n", $this->protocol, $this->status, $this->status_str[$this->status]);
        foreach ($this->headers as $k=>$v) {
            $head.= sprintf("%s: %s\r\n", $k, $v);
        }
        $head.= "\r\n";

        return $head.$this->body;
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    public function write($str)
    {
        $this->body.=$str;
        $this->setHeader("content-length", strlen($this->body));
    }
}
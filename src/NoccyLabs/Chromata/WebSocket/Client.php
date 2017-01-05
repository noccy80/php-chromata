<?php

namespace NoccyLabs\Chromata\WebSocket;


class Client
{
    const WS_SEC_GUID='258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    const CS_INITIAL = 0;
    const CS_HTTP = 1;
    const CS_WS = 2;

    protected $peer;
    protected $socket;
    protected $state = 0;
    protected $proxy;

    public function __construct($peer, $socket, WsProxy $proxy)
    {
        $this->peer = $peer;
        $this->socket = $socket;
        $this->proxy = $proxy;
    }
    public function hasSocket($socket)
    {
        return ($this->socket === $socket);
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function getPeer()
    {
        return $this->peer;
    }

    public function onIpcMessage($type, array $data)
    {

    }

    public function onClientMessage($raw)
    {
        if (false===strpos($raw,'|')) {
            return;
        }
        list($type,$data) = explode("|",$raw,2);
        $data = json_decode($data);
        if ($type == 'event') {
            $this->proxy->sendIpcMessage("event", (array)$data);
        } else {
            l_warn("Ignoring invalid message type %s",  $type);
        }
    }

    public function write($data)
    {
        $frame = new WsFrame();
        $frame->setFinal(true);
        $frame->setOpCode(WsFrame::OP_TEXT);
        $frame->setMasked(false);
        $frame->setData($data);
        $packed = $frame->packFrame();
        fwrite($this->socket, $packed);
    }

    public function read($data)
    {
        if ($this->state == self::CS_INITIAL) {
            l_debug("Initial handshake for %s", $this->peer);
            $request = new HttpRequest($data);
            if (!($request->getHeader("upgrade")=="websocket")) {
                $response = new HttpResponse(200, "/", "HTTP/1.0");
                $response->setHeader("content-type", "text/html");
                $response->setHeader("connection", "close");
                $response->write("<h1>Error</h1><p>This is a websocket server.</p>");
                fwrite($this->socket, (string)$response);
                return false;
            }
            $key = $request->getHeader("sec-websocket-key");
            $accept = $this->generateAcceptKey($key);
            $response = new HttpResponse(101, "/", "HTTP/1.1");
            $response->setHeader("Upgrade", "websocket");
            $response->setHeader("Connection", "upgrade");
            $response->setHeader("Sec-WebSocket-Accept", $accept);
            //l_info("client->response: %s", $response);
            fwrite($this->socket, (string)$response);
            $this->state = self::CS_WS;
            l_debug("Completed websocket handshake for %s", $this->peer);
            $this->proxy->sendIpcMessage("client.connect", [
                'peer' => $this->peer
            ]);
            return true;
        }
        //l_info("Received %d bytes", strlen($data));
        while ($data) {
            //l_info("Bytes left to process: %d", strlen($data));
            //$this->dumpDebug($data);
            try {
                $frame = WsFrame::fromRaw($data);
                $this->onClientMessage($frame->getData());
                // TODO: Process frame
                /*
                $resp = new WsFrame();
                $resp->setFinal(true);
                $resp->setOpCode(WsFrame::OP_TEXT);
                $resp->setMasked(false);
                $resp->setData($frame->getData());
                $dout = $resp->packFrame();
                //l_info("wrote %d bytes:", strlen($dout));
                //$this->dumpDebug($dout);
                @fwrite($this->socket, $dout);
                */
            } catch (\Exception $e) {
                l_error("%s", $e->getMessage());
                $this->socket = null;
                return false;
            }
        }
        return true;
    }

    public function __destruct()
    {
        $this->proxy->sendIpcMessage("client.disconnect", [
            'peer' => $this->peer
        ]);
        fclose($this->socket);
    }

    private function dumpDebug($data)
    {
        $datahex = null; 
        $datastr = null; 
        $offs = 0;
        for ($n = 0; $n < strlen($data); $n++) {
            if ($n && ($n%16==0)) {
                l_debug("%04x %-50s %s", $offs, $datahex, $datastr);
                $offs = $n;
                $datahex = null;
                $datastr = null;
            }
            $datahex.= sprintf("%02x ",ord($data[$n]));
            $datastr.= sprintf("%s ",ord($data[$n])>=32?$data[$n]:".");
        }
        if ($datastr) {
            l_debug("%04x %-50s %s", $offs, $datahex, $datastr);
        }
    }

    private function generateAcceptKey($challenge)
    {
        return base64_encode(sha1($challenge.self::WS_SEC_GUID, true));
    }
}


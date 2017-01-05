<?php

namespace NoccyLabs\Chromata\WebSocket;


/*
      0                   1                   2                   3
      0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
     +-+-+-+-+-------+-+-------------+-------------------------------+
     |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
     |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
     |N|V|V|V|       |S|             |   (if payload len==126/127)   |
     | |1|2|3|       |K|             |                               |
     +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
     |     Extended payload length continued, if payload len == 127  |
     + - - - - - - - - - - - - - - - +-------------------------------+
     |                               |Masking-key, if MASK set to 1  |
     +-------------------------------+-------------------------------+
     | Masking-key (continued)       |          Payload Data         |
     +-------------------------------- - - - - - - - - - - - - - - - +
     :                     Payload Data continued ...                :
     + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
     |                     Payload Data continued ...                |
     +---------------------------------------------------------------+
*/

class WsFrame
{
    const OP_CONTINUE = 0;
    const OP_TEXT = 1;
    const OP_BINARY = 2;

    protected $flag_fin;

    protected $opcode;

    protected $masked;

    protected $length;

    protected $data;    

    public static function fromRaw(&$raw)
    {
        $frame = new WsFrame();
        $frame->parseRawFrame($raw);
        return $frame;
    }

    public function packFrame()
    {
        $ds = strlen($this->data);

        if ($ds<126) {
            $sizew = 1;
            $size0 = $ds;
            $size1 = null;
        } elseif ($ds<65535) {
            $sizew = 2;
            $size0 = 126;
            $size1 = chr(($ds>>8)&0xFF) . chr($ds&0xFF);
        } else {
            $sizew = 8;
            $size0 = 127;
            $size1 = str_repeat(chr(0),4);
            $size1.= chr(($ds>>24)&0xFF) . chr(($ds>>16)&0xFF) . chr(($ds>>8)&0xFF) . chr($ds&0xFF);
        }

        $bit_fin = intval($this->flag_fin) << 7;
        $opcode = $this->opcode & 0x0F;
        $bit_mask = intval($this->masked) << 7;
        $res = chr($bit_fin | $opcode );
        $res.= chr($bit_mask | ($size0 & 0x7F));
        if ($sizew>1) {
            $res.= $size1;
        }

        if ($this->masked) {
            $key = chr(rand(0,255)).chr(rand(0,255)).chr(rand(0,255)).chr(rand(0,255));
            $res.= $key;
            $res.= $this->applyMask($this->data, $key);
        } else {
            $res.= $this->data;
        }

        return $res;
    }

    protected function parseRawFrame(&$raw)
    {
        // byte 1: fin[1], res[3], opcode[4]
        $byte1 = ord($raw[0]);
        $this->flag_fin = (bool)($byte1 & 0x80);
        $this->opcode = ($byte1 & 0x0F);
        // byte 2: masked[1], len[7]
        $byte2 = ord($raw[1]);
        $masked = (bool)($byte2 & 0x80);
        $size = ($byte2 & 0x7F);
        // byte 3-4: len[16]
        // byte 5-11: len[..]
        //l_debug("decoded base size is %d", $size);
        if ($size < 126) {
            $this->size = $size;
            $offs=2;
            //l_debug("real size = %d", $size);
        } elseif ($size < 127) {
            $byte3 = ord($raw[2]);
            $byte4 = ord($raw[3]);
            $this->size = $byte3<<8 | $byte4;
            $offs=4;
            //l_debug("real size = %d (%02x %02x)", $size, $byte3, $byte4);
        } else {
            $byte3 = ord($raw[2]);
            $byte4 = ord($raw[3]);
            $byte5 = ord($raw[4]);
            $byte6 = ord($raw[5]);
            $byte7 = ord($raw[6]);
            $byte8 = ord($raw[7]);
            $byte9 = ord($raw[8]);
            $byte10 = ord($raw[9]);
            $offs=8;
            l_warn("Big frame received, things may get weird if packetlen>65k");
            $this->size = $byte10 + $byte9<<8 + $byte8<<16 + $byte7<<24 + $byte6<<32 + $byte5<<40 + $byte4<<48;
        }
        // byte 11-15: mask_key[32]
        $mask_key = substr($raw,$offs,4);
        // byte 16-: data
        $hlen = $offs+4;
        if (strlen($raw)<$this->size+$hlen) {
            l_error("Bad frame size, header indicates %d bytes but packet only has %d bytes", $this->size, strlen($raw)-$hlen);
            throw new \Exception("Invalid frame");
        } else {
            $data = substr($raw, $hlen, $this->size);
            if ($masked) {
                $this->data = $this->applyMask($data,$mask_key);
            } else {
                $this->data = $data;
            }
        }

        $raw = substr($raw,$hlen + $this->size);

        //l_debug("Parsed frame from raw (%d bytes)", strlen($raw));
        //l_debug("  flag_fin=%d opcode=%d masked=%d size=%d", $this->flag_fin, $this->opcode, $this->masked, $this->size);
        //if ($this->opcode == 0x1) {
        //    l_debug("  text_frame=%s", $this->data);
        //}
        $this->masked = $masked;
    }

    public function setOpCode($op)
    {
        $this->opcode = $op;
    }

    public function getOpCode()
    {
        return $this->opcode;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function isMasked()
    {
        return $this->masked;
    }

    public function setMasked($state)
    {
        $this->masked = (bool)$state;
    }

    public function isFinal()
    {
        return $this->flag_fin;
    }

    public function setFinal($final)
    {
        $this->flag_fin = (bool)$final;
    }

    private function applyMask($data,$mask)
    {
        assert('strlen($mask)==4');
        $res = null;
        for ($n = 0; $n < strlen($data); $n++) {
            $b1 = ord($data[$n]);
            $b2 = ord($mask[$n%4]);
            $b3 = $b1 ^ $b2;
            $res.=chr($b3);
        }
        return $res;
    }


}

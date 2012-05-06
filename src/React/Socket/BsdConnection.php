<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class BsdConnection extends EventEmitter implements ConnectionInterface
{
    public $bufferSize = 4096;

    public $socket;

    private $loop;

    public function __construct($socket, LoopInterface $loop)
    {
        $this->socket = $socket;
        $this->loop   = $loop;
    }

    public function write($data)
    {
        $len = strlen($data);

        do {
            $sent = socket_write($this->socket, $data, $len);
            if (false === $sent) {
                $this->emit('error', array('Unable to write to socket', $this));
                return;
            }
            $len -= $sent;
            $data = substr($data, $sent);
        } while ($len > 0);
    }

    public function end()
    {
        $this->emit('end');
        $this->loop->removeStream($this->socket);

        socket_close($this->socket);
    }

    public function handleData($socket)
    {
        $data = $buf = '';

        $bytes = socket_recv($socket, $buf, $this->bufferSize, MSG_DONTWAIT);
        if ($bytes > 0) {
            $data = $buf;
            $this->emit('data', array($data));
        } else {
            $this->end();
        }
    }
}

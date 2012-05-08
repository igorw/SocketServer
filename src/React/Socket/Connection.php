<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class Connection extends EventEmitter implements ConnectionInterface
{
    public $bufferSize = 4096;
    public $socket;
    public $closed = false;
    private $loop;
    private $buffer;

    public function __construct($socket, LoopInterface $loop)
    {
        $this->socket = $socket;
        $this->loop = $loop;
        $this->buffer = new Buffer($this->socket, $this->loop);

        $that = $this;

        $this->buffer->on('error', function ($error) use ($that) {
            $that->emit('error', array($error, $that));
            $that->close();
        });
    }

    public function write($data)
    {
        if ($this->closed) {
            return;
        }

        $this->buffer->write($data);
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->emit('end', array($this));
        $this->loop->removeStream($this->socket);
        $this->buffer->removeAllListeners();
        $this->removeAllListeners();
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->closed = true;
    }

    public function end()
    {
        if ($this->closed) {
            return;
        }

        $that = $this;

        $this->buffer->on('end', function () use ($that) {
            $that->close();
        });

        $this->buffer->end();
    }

    public function handleData($socket)
    {
        $data = stream_socket_recvfrom($socket, $this->bufferSize);
        if ('' === $data || false === $data) {
            $this->end();
        } else {
            $this->emit('data', array($data, $this));
        }
    }
}

<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class BsdServer extends EventEmitter implements ServerInterface
{
    private $master;

    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function listen($port, $host = '127.0.0.1')
    {
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->master, SOL_SOCKET, SO_SNDBUF, 4096);

        socket_set_nonblock($this->master);

        if (false === socket_bind($this->master, $host, $port)) {
            $int = socket_last_error();
            $msg = socket_strerror($int);
            
            throw new ConnectionException($msg, $int);
        }
        socket_listen($this->master, 0);

        $that = $this;

        $this->loop->addReadStream($this->master, function ($master) use ($that) {
            if (false === ($newSocket = socket_accept($master))) {
                $that->emit('error', array('Error accepting new connection'));
                return;
            }

            $that->handleConnection($newSocket);
        });
    }

    public function handleConnection($socket)
    {
        socket_set_nonblock($socket);

        $client = $this->createConnection($socket);
        $this->loop->addReadStream($socket, array($client, 'handleData'));

        $this->emit('connect', array($client));
    }

    public function getPort()
    {
        // todo
        return -1;
    }

    public function shutdown()
    {
        $this->loop->removeStream($this->master);

        socket_shutdown($this->master, 2);
        socket_close($this->master);
    }

    public function createConnection($socket)
    {
        return new BsdConnection($socket, $this->loop);
    }
}

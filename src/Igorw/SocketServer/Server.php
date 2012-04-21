<?php

namespace Igorw\SocketServer;

use Evenement\EventEmitter;

class Server extends EventEmitter
{
    private $master;
    private $input;
    private $timeout;
    private $sockets = array();
    private $clients = array();

    // timeout = microseconds
    public function __construct($host, $port, $input = array(), $timeout = 1000000)
    {
        $this->master = stream_socket_server("tcp://$host:$port", $errno, $errstr);
        if (false === $this->master) {
            throw new ConnectionException($errstr, $errno);
        }

        $this->sockets[] = $this->master;

        $this->input = $input;
        if(!is_array($this->input)) {
            $this->input = array($input);
        }
        foreach($this->input as $name=>$stream) {
                $this->sockets[] = $stream;
        }

        $this->timeout = $timeout;
    }

    public function run()
    {
        // @codeCoverageIgnoreStart
        while (true) {
            $this->tick();
        }
        // @codeCoverageIgnoreEnd
    }

    public function tick()
    {
        $readySockets = $this->sockets;
        @stream_select($readySockets, $write = null, $except = null, 0, $this->timeout);
        foreach ($readySockets as $socket) {
            if ($this->master === $socket) {
                $newSocket = stream_socket_accept($this->master);
                if (false === $newSocket) {
                    echo('Socket error');
                    continue;
                }
                $this->handleConnection($newSocket);
            } elseif ($this->isInputStream($socket)) {
                $this->handleInput($socket);
            } else {
                $data = @stream_socket_recvfrom($socket, 4096);
                if ($data === '') {
                    $this->handleDisconnect($socket);
                } else {
                    $this->handleData($socket, $data);
                }
            }
        };
    }
    
    public function attachInput($name, $stream) {
        $this->input[$name] = $stream;
        $this->sockets[] = $stream;
    }

    private function handleConnection($socket)
    {
        $client = $this->createConnection($socket);

        $this->clients[(int) $socket] = $client;
        $this->sockets[] = $socket;

        $this->emit('connect', array($client));
    }

    private function handleInput($input)
    {
        if(isset($this->input[0]) && $input === $this->input[0]) {
            $name = 'input';
        }
        else {
            $streamName = \array_keys($this->input, $input);
            $name = 'input.'.$streamName[0];
        }
        $this->emit($name, array($input));
    }

    private function handleDisconnect($socket)
    {
        $this->close($socket);
    }

    private function handleData($socket, $data)
    {
        $client = $this->getClient($socket);

        $client->emit('data', array($data));
    }
    
    private function isInputStream($socket)
    {
        if(null !== $this->input) {
            if($this->input === $socket) {
                return true;
            }
            if(is_array($this->input) && in_array($socket, $this->input)) {
                return true;
            }
        }
    }

    public function getClient($socket)
    {
        return $this->clients[(int) $socket];
    }

    public function getClients()
    {
        return $this->clients;
    }

    public function write($data)
    {
        foreach ($this->clients as $conn) {
            $conn->write($data);
        }
    }

    public function close($socket)
    {
        $client = $this->getClient($socket);

        $client->emit('end');

        unset($this->clients[(int) $socket]);
        unset($client);

        $index = array_search($socket, $this->sockets);
        unset($this->sockets[$index]);

        fclose($socket);
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);
        return (int) substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        stream_socket_shutdown($this->master, STREAM_SHUT_RDWR);
    }

    public function createConnection($socket)
    {
        return new Connection($socket, $this);
    }
}

<?php

namespace Igorw\SocketServer;

use Evenement\EventEmitter;

class Connection extends EventEmitter
{
    protected $methods = array();

    public function __call($name, $arguments)
    {
        $this->checkMethod($name);

        $args = array();
        foreach ($arguments as $key => &$val) {
            $args[$key] = &$val;
        }

        $this->emit("before.{$name}", $args);

        return call_user_func_array($this->methods[$name], $args);
    }

    public function setMethod($name, $callback)
    {
        $this->methods[$name] = $callback;
    }

    public function removeMethod($name)
    {
        $this->checkMethod($name);

        unset($this->methods[$name]);
    }

    public function isMethod($name)
    {
        return (isset($this->methods[$name]));
    }

    public function getRawMethod($name)
    {
        $this->checkMethod($name);

        return $this->methods[$name];
    }

    protected function checkMethod($name)
    {
        if (!$this->isMethod($name)) {
            throw new \InvalidArgumentException("Method {$name} not found");
        }
    }
}

<?php

namespace React\Tests\Socket;

use React\Socket\Connection;

class ConnectionTest extends TestCase
{
    /**
     * @covers React\Socket\Connection::__construct
     */
    public function testConstructor()
    {
        $socket = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Connection($socket, $loop);
    }

    /**
     * @covers React\Socket\Connection::write
     */
    public function testWrite()
    {
        $socket = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();

        $conn = new Connection($socket, $loop);
        $conn->write("foo\n");

        rewind($socket);
        $this->assertSame("foo\n", fgets($socket));
    }

    /**
     * @covers React\Socket\Connection::write
     */
    public function testWriteError()
    {
        $socket = "Silly developer, you can't write to to a string!";
        $loop = $this->createWriteableLoopMock();

        $conn = new Connection($socket, $loop);
        $conn->on('error', $this->expectCallableOnce());
        $conn->write('Attempting to write to a string');
    }

    /**
     * @covers React\Socket\Connection::end
     */
    public function testEnd()
    {
        $socket = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Connection($socket, $loop);
        $conn->end();

        $this->assertFalse(is_resource($socket));
    }

    private function createWriteableLoopMock()
    {
        $loop = $this->createLoopMock();
        $loop
            ->expects($this->once())
            ->method('addWriteStream')
            ->will($this->returnCallback(function ($socket, $listener) {
                call_user_func($listener, $socket);
            }));

        return $loop;
    }

    private function createLoopMock()
    {
        return $this->getMock('React\EventLoop\LoopInterface');
    }
}

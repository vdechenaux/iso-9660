<?php

use ISO9660\RockRidge\DecoderSL;
use PHPUnit\Framework\TestCase;

class DecoderSLTest extends TestCase
{
    /**
     * @var DecoderSL
     */
    private $tested;

    public function setUp(): void
    {
        $this->tested = new DecoderSL();
    }

    public function test basic link()
    {
        $data = [];
        $this->tested->decodeData(chr(0).chr(0).chr(9).'hello.txt', 12, 1, $data);

        self::assertSame('hello.txt', $data['Symlink']);
    }

    public function test one component with continue flag()
    {
        $data = [];
        $this->tested->decodeData(
            chr(0). // NM flag = 0 = NO CONTINUE
                chr(1).chr(5).'hello'. // Component Flag = 1 = CONTINUE
                chr(0).chr(10).'-world.txt', // Component Flag = 0 = NO CONTINUE
            20,
            1,
            $data
        );

        self::assertSame('hello-world.txt', $data['Symlink']);
    }

    public function test one component stored in two blocks()
    {
        $data = [];
        $this->tested->decodeData(
            chr(0). // NM flag = 1 = CONTINUE (The symlink is split in parts)
            chr(1).chr(5).'hello', // Component Flag = 1 = CONTINUE (The component is split in parts)
            8,
            1,
            $data
        );

        $this->tested->decodeData(
            chr(0). // NM flag = 0 = NO CONTINUE (This is the end of the symlink)
            chr(0).chr(10).'-world.txt', // Component Flag = 0 = NO CONTINUE (This is the end of the component)
            9,
            1,
            $data
        );

        self::assertSame('hello-world.txt', $data['Symlink']);
    }

    public function test root dir()
    {
        $data = [];
        $this->tested->decodeData(
            chr(0).
            chr(8).chr(0). // Component Flag = 8 = ROOT
            chr(0).chr(9).'hello.txt',
            14,
            1,
            $data
        );

        self::assertSame('/hello.txt', $data['Symlink']);
    }

    public function test parent dir()
    {
        $data = [];
        $this->tested->decodeData(
            chr(0).
            chr(4).chr(0). // Component Flag = 4 = PARENT
            chr(0).chr(9).'hello.txt',
            14,
            1,
            $data
        );

        self::assertSame('../hello.txt', $data['Symlink']);
    }

    public function test current dir()
    {
        $data = [];
        $this->tested->decodeData(
            chr(0).
            chr(2).chr(0). // Component Flag = 2 = CURRENT
            chr(0).chr(9).'hello.txt',
            14,
            1,
            $data
        );

        self::assertSame('./hello.txt', $data['Symlink']);
    }
}

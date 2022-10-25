<?php

use ISO9660\RockRidge\DecoderNM;
use PHPUnit\Framework\TestCase;

class DecoderNMTest extends TestCase
{
    /**
     * @var DecoderNM
     */
    private $tested;

    public function setUp(): void
    {
        $this->tested = new DecoderNM();
    }

    public function test simple string()
    {
        $data = [];
        $this->tested->decodeData(chr(0).'hello.txt', 10, 1, $data);

        self::assertSame(
            [
                'FileName' => 'hello.txt',
                'FileNameLength' => 9,
            ],
            $data
        );
    }

    public function test two strings with continue flag on the first()
    {
        $data = [];
        $this->tested->decodeData(chr(1).'hello', 6, 1, $data);
        $this->tested->decodeData(chr(0).'-world.txt', 11, 1, $data);

        self::assertSame(
            [
                'FileName' => 'hello-world.txt',
                'FileNameLength' => 15,
            ],
            $data
        );
    }

    public function test two strings without continue flag ignore first string()
    {
        $data = [];
        $this->tested->decodeData(chr(0).'hello', 6, 1, $data);
        $this->tested->decodeData(chr(0).'-world.txt', 11, 1, $data);

        self::assertSame(
            [
                'FileName' => '-world.txt',
                'FileNameLength' => 10,
            ],
            $data
        );
    }

    public function test current dir ignored()
    {
        $data = [];
        $this->tested->decodeData(chr(2).'hello', 6, 1, $data);

        self::assertEmpty($data);
    }

    public function test parent dir ignored()
    {
        $data = [];
        $this->tested->decodeData(chr(4).'hello', 6, 1, $data);

        self::assertEmpty($data);
    }
}

<?php

use ISO9660\RockRidge\DecoderCL;
use PHPUnit\Framework\TestCase;

class DecoderCLTest extends TestCase
{
    /**
     * @var DecoderCL
     */
    private $tested;

    public function setUp()
    {
        $this->tested = new DecoderCL();
    }

    public function test decode()
    {
        $data = [
            'AdditionalDataFlags' => 0,
            'FileFlags' => 0,
        ];
        $this->tested->decodeData(pack('VN', 123456, 123456), 8, 1, $data);

        self::assertSame(
            [
                'AdditionalDataFlags' => \ISO9660\RockRidge\Flags::FLAG_CHILD_LINK,
                'FileFlags' => \ISO9660\Flags::FLAG_DIRECTORY,
                'ExtentLBA' => 123456,
            ],
            $data
        );
    }
}

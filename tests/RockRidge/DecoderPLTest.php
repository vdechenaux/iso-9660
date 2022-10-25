<?php

use ISO9660\RockRidge\DecoderPL;
use PHPUnit\Framework\TestCase;

Class DecoderPLTest extends TestCase
{
    /**
     * @var DecoderPL
     */
    private $tested;

    public function setUp(): void
    {
        $this->tested = new DecoderPL();
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
                'AdditionalDataFlags' => \ISO9660\RockRidge\Flags::FLAG_PARENT_LINK,
                'FileFlags' => \ISO9660\Flags::FLAG_DIRECTORY,
                'ExtentLBA' => 123456,
            ],
            $data
        );
    }
}

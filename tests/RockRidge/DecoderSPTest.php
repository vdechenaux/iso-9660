<?php

use ISO9660\RockRidge\DecoderSP;
use ISO9660\RockRidge\Flags;
use PHPUnit\Framework\TestCase;

class DecoderSPTest extends TestCase
{
    /**
     * @var DecoderSP
     */
    private $tested;

    public function setUp(): void
    {
        $this->tested = new DecoderSP();
    }

    public function test decode()
    {
        $data = [
            'AdditionalDataFlags' => 0,
        ];
        $this->tested->decodeData(chr(0xBE).chr(0xEF), 2, 1, $data);

        self::assertSame(
            [
                'AdditionalDataFlags' => Flags::FLAG_SUSP,
            ],
            $data
        );
    }

    public function test decode wrong data()
    {
        $data = [
            'AdditionalDataFlags' => 0,
        ];
        $this->tested->decodeData('AZ', 2, 1, $data);

        self::assertSame(
            [
                'AdditionalDataFlags' => 0,
            ],
            $data
        );
    }
}

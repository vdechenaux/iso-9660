<?php

use ISO9660\RockRidge\DecoderER;
use ISO9660\RockRidge\Flags;
use PHPUnit\Framework\TestCase;

class DecoderERTest extends TestCase
{
    /**
     * @var DecoderER
     */
    private $tested;

    public function setUp()
    {
        $this->tested = new DecoderER();
    }

    public function dataProvider() : array
    {
        return [
            [
                chr(10).chr(0).chr(0).chr(1).'IEEE_P1282',
            ],
            [
                chr(10).chr(0).chr(0).chr(1).'IEEE_1282',
            ],
            [
                chr(10).chr(0).chr(0).chr(1).'RRIP_1991A',
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function test decode($input)
    {
        $data = [
            'AdditionalDataFlags' => 0,
        ];
        $this->tested->decodeData($input, 14, 1, $data);

        self::assertSame(
            [
                'AdditionalDataFlags' => Flags::FLAG_ROCK_RIDGE,
            ],
            $data
        );
    }

    public function test decode wrong data()
    {
        $data = [
            'AdditionalDataFlags' => 0,
        ];
        $this->tested->decodeData('azerty', 6, 1, $data);

        self::assertSame(
            [
                'AdditionalDataFlags' => 0,
            ],
            $data
        );
    }
}

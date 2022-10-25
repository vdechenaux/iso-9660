<?php

use ISO9660\RockRidge\DecoderRR;
use ISO9660\RockRidge\Flags;
use PHPUnit\Framework\TestCase;

class DecoderRRTest extends TestCase
{
    /**
     * @var DecoderRR
     */
    private $tested;

    public function setUp(): void
    {
        $this->tested = new DecoderRR();
    }

    public function test decode()
    {
        $data = [
            'AdditionalDataFlags' => 0,
        ];
        $this->tested->decodeData('', 0, 1, $data);

        self::assertSame(
            [
                'AdditionalDataFlags' => Flags::FLAG_ROCK_RIDGE,
            ],
            $data
        );
    }
}

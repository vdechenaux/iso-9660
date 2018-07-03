<?php

use ISO9660\RockRidge\DecoderRE;
use ISO9660\RockRidge\Flags;
use PHPUnit\Framework\TestCase;

class DecoderRETest extends TestCase
{
    /**
     * @var DecoderRE
     */
    private $tested;

    public function setUp()
    {
        $this->tested = new DecoderRE();
    }

    public function test decode()
    {
        $data = ['AdditionalDataFlags' => 0];
        $this->tested->decodeData('', 0, 1, $data);

        self::assertSame(Flags::FLAG_RELOCATED, $data['AdditionalDataFlags']);
    }
}

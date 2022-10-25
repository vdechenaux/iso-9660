<?php

use ISO9660\RockRidge\DecoderPX;
use PHPUnit\Framework\TestCase;

class DecoderPXTest extends TestCase
{
    /**
     * @var DecoderPX
     */
    private $tested;

    public function setUp(): void
    {
        $this->tested = new DecoderPX();
    }

    public function test decode()
    {
        $inputData = base64_decode('/UEAAAAAQf0DAAAAAAAAA+gDAAAAAAPo6AMAAAAAA+g=');
        $data = [];
        $this->tested->decodeData($inputData, strlen($inputData), 1, $data);

        self::assertSame(
            [
                'FileMode' => 040775,
                'FileLinks' => 3,
                'FileUserID' => 1000,
                'FileGroupID' => 1000,
            ],
            $data['AdditionalDataFileMode']
        );
    }
}

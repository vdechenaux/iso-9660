<?php

use ISO9660\Util\DecDatetimeDecoder;
use PHPUnit\Framework\TestCase;

class DecDatetimeDecoderTest extends TestCase
{
    public function test bad length()
    {
        $this->expectException(InvalidArgumentException::class);

        DecDatetimeDecoder::decode('test');
    }

    public function test bad length with more than 17()
    {
        $this->expectException(InvalidArgumentException::class);

        DecDatetimeDecoder::decode('test test test test test');
    }

    public function test with wrong input of 17 chars()
    {
        self::assertNull(DecDatetimeDecoder::decode('12345testtesttest'));
    }

    public function test with good data()
    {
        self::assertSame(
            '2018-07-03T15:22:30+00:00',
            DecDatetimeDecoder::decode('2018070315223000'.chr(0))->format('c') // 0 = no offset
        );

        self::assertSame(
            '2018-07-03T13:22:30+00:00',
            DecDatetimeDecoder::decode('2018070315223000'.chr(8))->format('c') // 8 = UTC + 2
        );

        self::assertSame(
            '2018-07-03T17:22:30+00:00',
            DecDatetimeDecoder::decode('2018070315223000'.pack('c', -8))->format('c') // -8 = UTC - 2
        );
    }
}

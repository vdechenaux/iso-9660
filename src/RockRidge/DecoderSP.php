<?php

namespace ISO9660\RockRidge;

final class DecoderSP implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        if (substr($data, 0, 2) === chr(0xBE).chr(0xEF)) {
            $directoryData['AdditionalDataFlags'] |= Flags::FLAG_SUSP;
        }
    }
}

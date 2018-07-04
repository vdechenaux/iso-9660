<?php

namespace ISO9660\RockRidge;

final class DecoderRR implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $directoryData['AdditionalDataFlags'] |= Flags::FLAG_ROCK_RIDGE;
    }
}

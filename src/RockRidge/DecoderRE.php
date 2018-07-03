<?php

namespace ISO9660\RockRidge;

final class DecoderRE implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $directoryData['AdditionalDataFlags'] |= Flags::FLAG_RELOCATED;
    }
}

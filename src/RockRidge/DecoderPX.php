<?php

namespace ISO9660\RockRidge;

final class DecoderPX implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $format =
            'VFileMode/x4/' .
            'VFileLinks/x4/' .
            'VFileUserID/x4/' .
            'VFileGroupID';

        $directoryData['AdditionalDataFileMode'] = unpack($format, $data);
    }
}

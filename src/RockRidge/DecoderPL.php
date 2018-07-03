<?php

namespace ISO9660\RockRidge;

final class DecoderPL implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $parentLocation = unpack('VLocation', $data)['Location'];

        $directoryData['ExtentLBA'] = $parentLocation;
        $directoryData['FileFlags'] |= \ISO9660\Flags::FLAG_DIRECTORY;
        $directoryData['AdditionalDataFlags'] |= Flags::FLAG_PARENT_LINK;
    }
}

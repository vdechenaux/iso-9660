<?php

namespace ISO9660\RockRidge;

final class DecoderCL implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $childLocation = unpack('VLocation', $data)['Location'];

        $directoryData['ExtentLBA'] = $childLocation;
        $directoryData['FileFlags'] |= \ISO9660\Flags::FLAG_DIRECTORY;
        $directoryData['AdditionalDataFlags'] |= Flags::FLAG_CHILD_LINK;
    }
}

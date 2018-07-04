<?php

namespace ISO9660\RockRidge;

final class DecoderER implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $idLength = ord(substr($data, 0, 1));

        $id = substr($data, 4, $idLength); // Skip 4 bytes (LEN_ID, LEN_DES, LEN_SRC, EXT_VER)

        if ($id === 'RRIP_1991A' || $id === 'IEEE_P1282'  || $id === 'IEEE_1282') {
            $directoryData['AdditionalDataFlags'] |= Flags::FLAG_ROCK_RIDGE;
        }
    }
}

<?php

namespace ISO9660\RockRidge;

interface EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void;
}

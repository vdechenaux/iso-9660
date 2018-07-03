<?php

namespace ISO9660\RockRidge;

final class DecoderNM implements EntryDecoder
{
    private $filename = '';
    private $allowConcatenation = false;

    private const FLAG_CONTINUE = 0b00000001;
    private const FLAG_CURRENT  = 0b00000010;
    private const FLAG_PARENT   = 0b00000100;

    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $flags = ord(substr($data, 0, 1));

        if ($flags & (self::FLAG_CURRENT|self::FLAG_PARENT)) {
            return;
        }

        $name = substr($data, 1, $length - 1); // 1 = flags length
        if ($this->allowConcatenation) {
            $this->filename .= $name;
        } else {
            $this->filename = $name;
        }

        $this->allowConcatenation = (bool) ($flags & self::FLAG_CONTINUE);

        $directoryData['FileName'] = $this->filename;
        $directoryData['FileNameLength'] = strlen($this->filename);
    }
}

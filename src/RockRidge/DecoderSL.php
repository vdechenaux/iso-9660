<?php

namespace ISO9660\RockRidge;

final class DecoderSL implements EntryDecoder
{
    private $parts = [];
    private $currentPartTmpName = '';

    private const FLAG_CONTINUE = 0b00000001;
    private const FLAG_CURRENT  = 0b00000010;
    private const FLAG_PARENT   = 0b00000100;
    private const FLAG_ROOT     = 0b00001000;

    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $flags = ord(substr($data, 0, 1));

        $position = 1;

        while ($position < ($length-1)) { // Ensure there is at least 2 bytes to read
            $componentFlags = ord(substr($data, $position++, 1));
            $componentLength = ord(substr($data, $position++, 1));

            $content = substr($data, $position, $componentLength);
            $position += $componentLength;

            if ($componentFlags & self::FLAG_CURRENT) {
                $content = '.';
            } elseif ($componentFlags & self::FLAG_PARENT) {
                $content = '..';
            } elseif ($componentFlags & self::FLAG_ROOT) {
                $content = '/';
            }

            $this->currentPartTmpName .= $content;

            if (!($componentFlags & self::FLAG_CONTINUE)) {
                $this->parts[] = $this->currentPartTmpName;
                $this->currentPartTmpName = '';
            }
        }

        if (!($flags & self::FLAG_CONTINUE)) {
            $directoryData['Symlink'] = implode('/', $this->parts);
            $this->parts = [];

            if (substr($directoryData['Symlink'], 0, 2) === '//') {
                $directoryData['Symlink'] = substr($directoryData['Symlink'], 1);
            }
        }
    }
}

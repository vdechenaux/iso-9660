<?php

namespace ISO9660\RockRidge;

final class Decoder
{
    public static function decodeData(string $rawData, array &$directoryData, $stream, int $blockSize) : void
    {
        /** @var EntryDecoder[] $decoders */
        $decoders = [];
        $position = 0;

        do {
            $type = substr($rawData, $position, 2);
            $length = ord(substr($rawData, $position+2, 1));
            $version = ord(substr($rawData, $position+3, 1));
            $data = substr($rawData, $position+4, $length-4);
            $position += $length;

            if (class_exists(self::class.$type)) {
                if (!isset($decoders[$type])) {
                    $className = self::class.$type;
                    $decoders[$type] = new $className();
                }

                $decoders[$type]->decodeData($data, $length-4, $version, $directoryData);
            } elseif ($type === 'CE') { // Load additional data
                $currentPos = ftell($stream);

                $format =
                    'VLocation/x4/' .
                    'VOffset/x4/' .
                    'VAreaLength';
                $CEdata = unpack($format, $data);

                fseek($stream, ($blockSize * $CEdata['Location']) + $CEdata['Offset']);
                $rawData .= fread($stream, $CEdata['AreaLength']);
                fseek($stream, $currentPos);
            } elseif ($length === 0) {
              // When an ISO has non-RockRidge data at the end of the
              // directory entry, and the length bytes just happen to be 0,
              // return - otherwise we'll loop forever.
              return;
            }

            // Skip potential padding byte
            if (substr($rawData, $position, 1) === chr(0)) {
                $position++;
            }
        }  while ($position < strlen($rawData) - 3); // Ensure there is at least 4 bytes to read
    }
}

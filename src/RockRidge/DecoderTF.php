<?php

namespace ISO9660\RockRidge;

use ISO9660\Util\DecDatetimeDecoder;

final class DecoderTF implements EntryDecoder
{
    public function decodeData(string $data, int $length, int $version, array &$directoryData) : void
    {
        $flags = ord(substr($data, 0, 1));
        $position = 1;

        $creationTimeRecorded   = (bool) ($flags & 0b00000001);
        $modifyTimeRecorded     = (bool) ($flags & 0b00000010);
        $accessTimeRecorded     = (bool) ($flags & 0b00000100);
        $attributesTimeRecorded = (bool) ($flags & 0b00001000);
        $backupTimeRecorded     = (bool) ($flags & 0b00010000);
        $expirationTimeRecorded = (bool) ($flags & 0b00100000);
        $effectiveTimeRecorded  = (bool) ($flags & 0b01000000);
        $longFormUsed           = (bool) ($flags & 0b10000000);

        $timestampLength = $longFormUsed ? 17 : 7;

        $creationTime = $modifyTime = $accessTime = $attributesTime = $backupTime = $expirationTime = $effectiveTime = null;

        if ($creationTimeRecorded) {
            $creationTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
            $position += $timestampLength;
        }

        if ($modifyTimeRecorded) {
            $modifyTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
            $position += $timestampLength;
        }

        if ($accessTimeRecorded) {
            $accessTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
            $position += $timestampLength;
        }

        if ($attributesTimeRecorded) {
            $attributesTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
            $position += $timestampLength;
        }

        if ($backupTimeRecorded) {
            $backupTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
            $position += $timestampLength;
        }

        if ($expirationTimeRecorded) {
            $expirationTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
            $position += $timestampLength;
        }

        if ($effectiveTimeRecorded) {
            $effectiveTime = $this->decodeTimestamp(substr($data, $position, $timestampLength), $longFormUsed);
        }

        $directoryData['AdditionalDataTimestamps'] = [
            'Creation' => $creationTime,
            'Modify' => $modifyTime,
            'Access' => $accessTime,
            'Attributes' => $attributesTime,
            'Backup' => $backupTime,
            'Expiration' => $expirationTime,
            'Effective' => $effectiveTime,
        ];
    }

    private function decodeTimestamp(string $data, bool $longFormUsed) : ?\DateTimeInterface
    {
        if ($longFormUsed) {
            return DecDatetimeDecoder::decode($data);
        }

        return $this->decodeShortTimestamp($data);
    }

    private function decodeShortTimestamp(string $data) : \DateTimeInterface
    {
        $format =
            'CYear/' .
            'CMonth/' .
            'CDay/' .
            'CHour/' .
            'CMinute/' .
            'CSecond/' .
            'cOffset';
        $timestampData = unpack($format, $data);

        $timestampData['Year'] += 1900;

        $result = new \DateTime();
        $result
            ->setTimezone(new \DateTimeZone('UTC'))
            ->setDate($timestampData['Year'], $timestampData['Month'], $timestampData['Day'])
            ->setTime($timestampData['Hour'], $timestampData['Minute'], $timestampData['Second'])
        ;

        if ($timestampData['Offset'] !== 0) {
            $minutesOffset = abs($timestampData['Offset']) * 15;
            $interval = new \DateInterval('PT'.$minutesOffset.'M');

            if ($timestampData['Offset'] > 0) {
                $result->sub($interval);
            } else {
                $result->add($interval);
            }
        }

        return \DateTimeImmutable::createFromMutable($result);
    }
}

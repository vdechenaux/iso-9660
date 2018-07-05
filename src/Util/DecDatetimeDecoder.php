<?php

namespace ISO9660\Util;

final class DecDatetimeDecoder
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function decode(string $input) : ?\DateTimeInterface
    {
        if (strlen($input) !== 17) {
            throw new \InvalidArgumentException('Input must be 17 characters long');
        }

        $matches = [];
        if (!preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})/', $input, $matches)) {
            return null;
        }

        $result = new \DateTime();
        $result
            ->setTimezone(new \DateTimeZone('UTC'))
            ->setDate($matches[1], $matches[2], $matches[3])
            ->setTime($matches[4], $matches[5], $matches[6])
        ;

        $offset = unpack('c', $input[16])[1];
        if ($offset !== 0) {
            $minutesOffset = abs($offset) * 15;
            $interval = new \DateInterval('PT'.$minutesOffset.'M');

            if ($offset > 0) {
                $result->sub($interval);
            } else {
                $result->add($interval);
            }
        }

        return \DateTimeImmutable::createFromMutable($result);
    }
}

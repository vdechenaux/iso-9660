<?php

namespace ISO9660\RockRidge;

final class Flags
{
    public const FLAG_CHILD_LINK    = 0b00000001;
    public const FLAG_PARENT_LINK   = 0b00000010;
    public const FLAG_RELOCATED     = 0b00000100;
    public const FLAG_SUSP          = 0b00001000;
    public const FLAG_ROCK_RIDGE    = 0b00010000;
}

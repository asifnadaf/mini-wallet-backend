<?php

namespace App\Enums;

enum ServerStatus: string
{
    case UP = 'up';
    case DOWN = 'down';
    case UNKNOWN = 'unknown';
}

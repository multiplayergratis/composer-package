<?php

declare(strict_types=1);

namespace MultiplayerGratis;

enum PowerSignal: string
{
    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';
    case Kill = 'kill';
}

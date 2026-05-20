<?php

namespace App\Enums;

enum UserChallengeStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}

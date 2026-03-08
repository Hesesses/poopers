<?php

namespace App\Enums;

enum AnomalyType: string
{
    case MaxExceeded = 'max_exceeded';
    case StepsDecreased = 'steps_decreased';
    case VelocityExceeded = 'velocity_exceeded';
    case RoundNumber = 'round_number';
    case LargeJump = 'large_jump';
    case AttestFailed = 'attest_failed';
    case HourlyMismatch = 'hourly_mismatch';
    case SuspiciousNight = 'suspicious_night';
}

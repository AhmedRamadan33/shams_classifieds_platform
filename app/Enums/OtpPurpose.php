<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpPurpose: string
{
    case Register = 'register';
    case Reset = 'reset';
}

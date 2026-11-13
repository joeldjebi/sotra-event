<?php

namespace App\Enums;

/**
 * Population visée par un code OTP.
 */
enum OtpAudience: string
{
    case Customer = 'customer';
    case Staff = 'staff';
}

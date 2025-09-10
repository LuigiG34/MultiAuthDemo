<?php

namespace App\Enum;

enum AuthProvider: string
{
    case LOCAL    = 'local';
    case GOOGLE   = 'google';
    case APPLE    = 'apple';
    case FACEBOOK = 'facebook';
}

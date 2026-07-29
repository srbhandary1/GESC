<?php

declare(strict_types=1);

namespace App\Authentication;

enum AuthenticationTransport: string
{
    case WebSession = 'web_session';
    case MobilePassport = 'mobile_passport';
}

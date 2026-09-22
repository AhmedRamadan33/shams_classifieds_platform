<?php

declare(strict_types=1);

namespace App\Enums;

enum ListingEventType: string
{
    case View = 'view';
    case PhoneClick = 'phone_click';
    case WhatsappClick = 'whatsapp_click';
}

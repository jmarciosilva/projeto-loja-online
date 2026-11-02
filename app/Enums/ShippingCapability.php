<?php

namespace App\Enums;

enum ShippingCapability: string
{
    case Quote = 'quote';
    case Purchase = 'purchase';
    case Label = 'label';
    case Tracking = 'tracking';
    case Cancellation = 'cancellation';
}

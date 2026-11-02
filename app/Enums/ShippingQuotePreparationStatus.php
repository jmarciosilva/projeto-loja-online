<?php

namespace App\Enums;

enum ShippingQuotePreparationStatus: string
{
    case Ready = 'ready';
    case NotRequired = 'not_required';
    case Unavailable = 'unavailable';
}

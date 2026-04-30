<?php

namespace App\Constants;

enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';

    case ISSUED = 'ISSUED';

    case OVERDUE = 'OVERDUE';

    case CANCELLED = 'CANCELLED';
}

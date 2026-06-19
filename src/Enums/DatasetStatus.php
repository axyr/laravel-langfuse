<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Enums;

enum DatasetStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}

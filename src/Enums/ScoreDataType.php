<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Enums;

enum ScoreDataType: string
{
    case NUMERIC = 'NUMERIC';
    case BOOLEAN = 'BOOLEAN';
    case CATEGORICAL = 'CATEGORICAL';
}

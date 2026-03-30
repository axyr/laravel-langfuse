<?php

declare(strict_types=1);

namespace Langfuse\Enums;

enum ScoreDataType: string
{
    case NUMERIC = 'NUMERIC';
    case BOOLEAN = 'BOOLEAN';
    case CATEGORICAL = 'CATEGORICAL';
}

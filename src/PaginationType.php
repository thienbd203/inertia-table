<?php

namespace Musing\InertiaTable;

enum PaginationType: string
{
    case Full = 'full';
    case Simple = 'simple';
    case Cursor = 'cursor';
}

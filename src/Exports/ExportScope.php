<?php

namespace Musing\InertiaTable\Exports;

enum ExportScope: string
{
    case All = 'all';
    case Filtered = 'filtered';
    case Selected = 'selected';
}

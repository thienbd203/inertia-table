<?php

namespace Toolbelt\InertiaTable;

enum Variant: string
{
    case Default = 'default';
    case Danger = 'danger';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
}

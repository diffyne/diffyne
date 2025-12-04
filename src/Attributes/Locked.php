<?php

namespace Diffyne\Attributes;

use Attribute;

/**
 * Mark a property as locked (cannot be updated from client).
 * Use for server-controlled properties like totals, computed values, etc.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Locked
{
    //
}

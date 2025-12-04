<?php

namespace Diffyne\Attributes;

use Attribute;

/**
 * Mark a property as computed (derived from other properties, not stored in state).
 * Computed properties are excluded from state serialization and cannot be updated from client.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Computed
{
    //
}

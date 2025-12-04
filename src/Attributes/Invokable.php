<?php

namespace Diffyne\Attributes;

use Attribute;

/**
 * Mark a method as invokable from the client.
 * Only methods with this attribute can be invoked via Diffyne actions.
 * This provides explicit whitelist-based security.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Invokable
{
    //
}

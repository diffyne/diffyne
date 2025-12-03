<?php

namespace Diffyne\Attributes;

use Attribute;

/**
 * Mark a method as an event listener.
 *
 * @example
 * #[On('user-updated')]
 * public function handleUserUpdate($userId) { }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class On
{
    public function __construct(
        public string $event
    ) {}
}

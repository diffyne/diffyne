<?php

namespace Diffyne\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Lazy
{
    /**
     * Create a new Lazy attribute instance.
     *
     * @param  string  $placeholder  Placeholder content to show while loading (HTML string or component class)
     */
    public function __construct(
        public ?string $placeholder = null,
    ) {}
}

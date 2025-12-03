<?php

namespace Diffyne\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class QueryString
{
    /**
     * Create a new query string binding attribute.
     *
     * @param  string|null  $as  Custom query parameter name
     * @param  bool  $history  Whether to push to browser history (default: true)
     * @param  bool  $keep  Whether to keep empty values in URL (default: false)
     */
    public function __construct(
        public ?string $as = null,
        public bool $history = true,
        public bool $keep = false
    ) {}
}

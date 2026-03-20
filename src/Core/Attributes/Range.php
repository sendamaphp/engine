<?php

namespace Sendama\Engine\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Range
{
    public function __construct(
        public int|float $min,
        public int|float $max,
        public int|float $step = 1,
    ) {
    }
}

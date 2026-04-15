<?php

namespace Sendama\Engine\Core\Coroutines;

final class CoroutineContext
{
    public function __construct(
        public readonly CoroutinePhase $phase,
        public readonly int $updateTick,
        public readonly int $fixedUpdateTick,
        public readonly float $time,
    ) {
    }
}

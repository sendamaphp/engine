<?php

namespace Sendama\Engine\Core\Coroutines;

final class WaitForFixedUpdate implements CoroutineYieldInstructionInterface
{
    public function __construct(private readonly int $minimumFixedUpdateTick = 1)
    {
    }

    public function schedule(CoroutineContext $context): CoroutineYieldInstructionInterface
    {
        return new self($context->fixedUpdateTick + 1);
    }

    public function isReady(CoroutineContext $context): bool
    {
        return $context->phase === CoroutinePhase::FixedUpdate
            && $context->fixedUpdateTick >= $this->minimumFixedUpdateTick;
    }
}

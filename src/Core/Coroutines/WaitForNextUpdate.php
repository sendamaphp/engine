<?php

namespace Sendama\Engine\Core\Coroutines;

final class WaitForNextUpdate implements CoroutineYieldInstructionInterface
{
    public function __construct(private readonly int $minimumUpdateTick = 1)
    {
    }

    public function schedule(CoroutineContext $context): CoroutineYieldInstructionInterface
    {
        return new self($context->updateTick + 1);
    }

    public function isReady(CoroutineContext $context): bool
    {
        return $context->phase === CoroutinePhase::Update
            && $context->updateTick >= $this->minimumUpdateTick;
    }
}

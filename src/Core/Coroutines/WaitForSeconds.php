<?php

namespace Sendama\Engine\Core\Coroutines;

final class WaitForSeconds implements CoroutineYieldInstructionInterface
{
    public function __construct(
        private readonly float $seconds,
        private readonly int $minimumUpdateTick = 1,
        private readonly ?float $resumeAt = null,
    ) {
    }

    public function schedule(CoroutineContext $context): CoroutineYieldInstructionInterface
    {
        return new self(
            $this->seconds,
            $context->updateTick + 1,
            $context->time + max(0.0, $this->seconds),
        );
    }

    public function isReady(CoroutineContext $context): bool
    {
        return $context->phase === CoroutinePhase::Update
            && $context->updateTick >= $this->minimumUpdateTick
            && $context->time >= ($this->resumeAt ?? INF);
    }
}

<?php

namespace Sendama\Engine\Core\Behaviours;

use Generator;
use InvalidArgumentException;
use Sendama\Engine\Core\Component;
use Sendama\Engine\Core\Coroutines\CoroutineContext;
use Sendama\Engine\Core\Coroutines\CoroutinePhase;
use Sendama\Engine\Core\Coroutines\CoroutineYieldInstructionInterface;
use Sendama\Engine\Core\Coroutines\WaitForNextUpdate;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Time;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;

/**
 * Behaviour class. This class is the base class for all behaviours in the engine.
 */
abstract class Behaviour extends Component
{
    /**
     * @var array<int, array{generator: Generator, instruction: CoroutineYieldInstructionInterface}>
     */
    protected array $coroutines = [];

    protected int $updateTick = 0;
    protected int $fixedUpdateTick = 0;
    protected float $coroutineTime = 0.0;
    protected bool $coroutinesSuspended = false;

    public SceneInterface $activeScene {
        get {
            return $this->getGameObject()->activeScene;
        }
    }

    public SceneInterface $scene {
        get {
            return $this->getGameObject()->getScene();
        }
    }

    public final function __construct(GameObject $gameObject)
    {
        parent::__construct($gameObject);
    }

    public function startCoroutine(Generator $routine): Generator
    {
        $coroutineId = spl_object_id($routine);

        if (isset($this->coroutines[$coroutineId])) {
            return $routine;
        }

        $routine->rewind();

        if (!$routine->valid()) {
            return $routine;
        }

        $this->coroutines[$coroutineId] = [
            'generator' => $routine,
            'instruction' => $this->resolveYieldInstruction($routine->current()),
        ];

        return $routine;
    }

    public function stopCoroutine(Generator $routine): void
    {
        unset($this->coroutines[spl_object_id($routine)]);
    }

    public function stopAllCoroutines(): void
    {
        $this->coroutines = [];
    }

    protected function beforeUpdateCycle(): void
    {
        $this->updateTick++;
        $this->coroutineTime += max(0.0, Time::getDeltaTime());
    }

    protected function afterUpdateCycle(): void
    {
        $this->tickCoroutines(new CoroutineContext(
            CoroutinePhase::Update,
            $this->updateTick,
            $this->fixedUpdateTick,
            $this->coroutineTime,
        ));
    }

    protected function beforeFixedUpdateCycle(): void
    {
        $this->fixedUpdateTick++;
    }

    protected function afterFixedUpdateCycle(): void
    {
        $this->tickCoroutines(new CoroutineContext(
            CoroutinePhase::FixedUpdate,
            $this->updateTick,
            $this->fixedUpdateTick,
            $this->coroutineTime,
        ));
    }

    protected function afterSuspend(): void
    {
        $this->coroutinesSuspended = true;
    }

    protected function afterResume(): void
    {
        $this->coroutinesSuspended = false;
    }

    protected function afterStop(): void
    {
        $this->stopAllCoroutines();
    }

    /**
     * Called when the collider enters another collider.
     *
     * @param CollisionInterface $collision The collision.
     * @return void
     */
    public function onCollisionEnter(CollisionInterface $collision): void
    {
        // Override this method to handle collision enter events.
    }

    /**
     * Called when the collider exits another collider.
     *
     * @param CollisionInterface $collision The collision.
     * @return void
     */
    public function onCollisionExit(CollisionInterface $collision): void
    {
        // Override this method to handle collision exit events.
    }

    /**
     * Called when the collider stays in another collider.
     *
     * @param CollisionInterface $collision The collision.
     * @return void
     */
    public function onCollisionStay(CollisionInterface $collision): void
    {
        // Override this method to handle collision stay events.
    }

    /**
     * Called when the collider enters a trigger.
     *
     * @param ColliderInterface $collider The collider.
     * @return void
     */
    public function onTriggerEnter(ColliderInterface $collider): void
    {
        // Override this method to handle trigger enter events.
    }

    /**
     * Called when the collider exits a trigger.
     *
     * @param ColliderInterface $collider The collider.
     * @return void
     */
    public function onTriggerExit(ColliderInterface $collider): void
    {
        // Override this method to handle trigger exit events.
    }

    /**
     * Called when the collider stays in a trigger.
     *
     * @param ColliderInterface $collider The collider.
     * @return void
     */
    public function onTriggerStay(ColliderInterface $collider): void
    {
        // Override this method to handle trigger stay events.
    }

    private function tickCoroutines(CoroutineContext $context): void
    {
        if ($this->coroutinesSuspended || $this->coroutines === []) {
            return;
        }

        foreach (array_keys($this->coroutines) as $coroutineId) {
            $state = $this->coroutines[$coroutineId] ?? null;

            if (!is_array($state)) {
                continue;
            }

            if (!$state['instruction']->isReady($context)) {
                continue;
            }

            $generator = $state['generator'];
            $generator->next();

            if (!$generator->valid()) {
                unset($this->coroutines[$coroutineId]);
                continue;
            }

            $this->coroutines[$coroutineId]['instruction'] = $this->resolveYieldInstruction($generator->current());
        }
    }

    private function resolveYieldInstruction(mixed $yieldedValue): CoroutineYieldInstructionInterface
    {
        $context = new CoroutineContext(
            CoroutinePhase::Update,
            $this->updateTick,
            $this->fixedUpdateTick,
            $this->coroutineTime,
        );

        if ($yieldedValue === null) {
            return (new WaitForNextUpdate())->schedule($context);
        }

        if ($yieldedValue instanceof CoroutineYieldInstructionInterface) {
            return $yieldedValue->schedule($context);
        }

        throw new InvalidArgumentException(
            sprintf(
                'Coroutines must yield null or a %s, %s yielded instead.',
                CoroutineYieldInstructionInterface::class,
                get_debug_type($yieldedValue),
            ),
        );
    }
}

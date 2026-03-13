<?php

namespace Sendama\Engine\Physics;

use Sendama\Engine\Core\Time;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Metadata\PhysicsMaterialMetadata;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;

/**
 * A Rigidbody provides force-driven movement that is still constrained by the engine's collider checks.
 *
 * The implementation keeps float simulation state internally so forces and drag can accumulate smoothly,
 * while final movement is resolved against the integer terminal grid one cell at a time.
 */
class Rigidbody extends Collider
{
    private const float DEFAULT_FIXED_DELTA_TIME = 0.0166666667;
    private const float VELOCITY_EPSILON = 0.0001;

    protected float $mass = 1.0;
    protected float $drag = 0.0;
    protected float $angularDrag = 0.0;
    protected bool $useGravity = false;
    protected bool $freezePositionX = false;
    protected bool $freezePositionY = false;
    protected bool $freezeRotation = false;

    protected float $velocityX = 0.0;
    protected float $velocityY = 0.0;
    protected float $angularVelocity = 0.0;
    protected float $accumulatedForceX = 0.0;
    protected float $accumulatedForceY = 0.0;
    protected float $accumulatedTorque = 0.0;
    protected float $simulatedPositionX = 0.0;
    protected float $simulatedPositionY = 0.0;
    protected float $simulatedRotationX = 0.0;
    protected float $simulatedRotationY = 0.0;
    protected bool $simulationStateInitialized = false;
    /**
     * @var array<string, CollisionInterface>
     */
    protected array $previousCollisions = [];
    /**
     * @var array<string, CollisionInterface>
     */
    protected array $currentCollisions = [];

    protected ?Vector2 $queuedPositionTarget = null;
    protected ?Vector2 $queuedRotationTarget = null;

    public function onStart(): void
    {
        $this->syncSimulationState(force: true);
    }

    public function onFixedUpdate(): void
    {
        $this->simulate();
    }

    /**
     * Moves the rigidbody toward an absolute world position on the next physics step.
     *
     * @param Vector2 $position
     * @return void
     */
    public function movePosition(Vector2 $position): void
    {
        $targetPosition = Vector2::getClone($position);

        // Guard against callers mutating the live transform position before queueing a constrained move.
        if ($position === $this->getTransform()->getPosition()) {
            $this->restoreTransformPositionFromSimulationState();
        }

        $this->queuedPositionTarget = $targetPosition;
    }

    /**
     * Moves the rigidbody toward an absolute world position and rotation on the next physics step.
     *
     * @param Vector2 $position
     * @param Vector2 $rotation
     * @return void
     */
    public function movePositionAndRotation(Vector2 $position, Vector2 $rotation): void
    {
        $this->movePosition($position);
        $this->moveRotation($rotation);
    }

    /**
     * Rotates the rigidbody toward an absolute rotation on the next physics step.
     *
     * @param Vector2 $rotation
     * @return void
     */
    public function moveRotation(Vector2 $rotation): void
    {
        $this->queuedRotationTarget = Vector2::getClone($rotation);
    }

    /**
     * Adds a world-space force to the rigidbody.
     *
     * @param Vector2 $force
     * @return void
     */
    public function addForce(Vector2 $force): void
    {
        $this->accumulatedForceX += $force->getX();
        $this->accumulatedForceY += $force->getY();
    }

    /**
     * Adds a force in the rigidbody's local space.
     *
     * @param Vector2 $force
     * @return void
     */
    public function addRelativeForce(Vector2 $force): void
    {
        [$x, $y] = $this->rotateVectorToWorld($force->getX(), $force->getY());
        $this->addForceComponents($x, $y);
    }

    /**
     * Adds a force at the given world-space position and accumulates torque.
     *
     * @param Vector2 $force
     * @param Vector2 $position
     * @return void
     */
    public function addForceAtPosition(Vector2 $force, Vector2 $position): void
    {
        $this->addForce($force);

        $bounds = $this->getBoundingBox();
        $centerX = $bounds->getX() + ($bounds->getWidth() / 2);
        $centerY = $bounds->getY() + ($bounds->getHeight() / 2);
        $offsetX = $position->getX() - $centerX;
        $offsetY = $position->getY() - $centerY;

        $this->accumulatedTorque += ($offsetX * $force->getY()) - ($offsetY * $force->getX());
    }

    /**
     * Adds a local-space force at a local-space offset from the rigidbody center.
     *
     * @param Vector2 $force
     * @param Vector2 $position
     * @return void
     */
    public function addRelativeForceAtPosition(Vector2 $force, Vector2 $position): void
    {
        [$forceX, $forceY] = $this->rotateVectorToWorld($force->getX(), $force->getY());
        [$offsetX, $offsetY] = $this->rotateVectorToWorld($position->getX(), $position->getY());
        $worldPosition = new Vector2(
            (int)round($this->simulatedPositionX + $offsetX),
            (int)round($this->simulatedPositionY + $offsetY)
        );

        $this->addForceAtPosition(
            new Vector2((int)round($forceX), (int)round($forceY)),
            $worldPosition
        );
    }

    /**
     * Adds force along the world-space x-axis.
     *
     * @param int|float $force
     * @return void
     */
    public function addForceX(int|float $force): void
    {
        $this->accumulatedForceX += $force;
    }

    /**
     * Adds force along the world-space y-axis.
     *
     * @param int|float $force
     * @return void
     */
    public function addForceY(int|float $force): void
    {
        $this->accumulatedForceY += $force;
    }

    /**
     * Adds force along the rigidbody's local x-axis.
     *
     * @param int|float $force
     * @return void
     */
    public function addRelativeForceX(int|float $force): void
    {
        [$x, $y] = $this->rotateVectorToWorld($force, 0.0);
        $this->addForceComponents($x, $y);
    }

    /**
     * Adds force along the rigidbody's local y-axis.
     *
     * @param int|float $force
     * @return void
     */
    public function addRelativeForceY(int|float $force): void
    {
        [$x, $y] = $this->rotateVectorToWorld(0.0, $force);
        $this->addForceComponents($x, $y);
    }

    /**
     * @inheritDoc
     */
    public function configure(array $options = []): void
    {
        parent::configure($options);

        if (array_key_exists('mass', $options)) {
            $this->setMass((float)$options['mass']);
        }

        if (array_key_exists('drag', $options)) {
            $this->setDrag((float)$options['drag']);
        }

        if (array_key_exists('angularDrag', $options)) {
            $this->setAngularDrag((float)$options['angularDrag']);
        }

        if (array_key_exists('useGravity', $options)) {
            $this->setUseGravity((bool)$options['useGravity']);
        }
    }

    /**
     * Advances the rigidbody simulation by one fixed step.
     *
     * @return void
     */
    public function simulate(): void
    {
        $this->syncSimulationState();
        $this->currentCollisions = [];

        $deltaTime = $this->resolveDeltaTime();
        $this->integrateForces($deltaTime);

        $targetPositionX = $this->queuedPositionTarget?->getX() ?? ($this->simulatedPositionX + ($this->velocityX * $deltaTime));
        $targetPositionY = $this->queuedPositionTarget?->getY() ?? ($this->simulatedPositionY + ($this->velocityY * $deltaTime));

        if ($this->freezePositionX) {
            $targetPositionX = $this->simulatedPositionX;
            $this->velocityX = 0.0;
        }

        if ($this->freezePositionY) {
            $targetPositionY = $this->simulatedPositionY;
            $this->velocityY = 0.0;
        }

        [$this->simulatedPositionX, $this->simulatedPositionY] = $this->applyLinearMotion(
            $targetPositionX,
            $targetPositionY,
            $deltaTime
        );

        $targetRotationX = $this->queuedRotationTarget?->getX() ?? ($this->simulatedRotationX + ($this->angularVelocity * $deltaTime));
        $targetRotationY = $this->queuedRotationTarget?->getY() ?? $this->simulatedRotationY;

        if ($this->freezeRotation) {
            $targetRotationX = $this->simulatedRotationX;
            $targetRotationY = $this->simulatedRotationY;
            $this->angularVelocity = 0.0;
        }

        $this->applyRotationalMotion($targetRotationX, $targetRotationY);
        $this->dispatchExitedCollisions();
        $this->previousCollisions = $this->currentCollisions;
        $this->clearQueuedMovement();
    }

    /**
     * Returns the current world-space velocity rounded to grid units.
     *
     * @return Vector2
     */
    public function getVelocity(): Vector2
    {
        return new Vector2(
            (int)round($this->velocityX),
            (int)round($this->velocityY)
        );
    }

    /**
     * Sets the current world-space velocity.
     *
     * @param Vector2 $velocity
     * @return void
     */
    public function setVelocity(Vector2 $velocity): void
    {
        $this->velocityX = $velocity->getX();
        $this->velocityY = $velocity->getY();
    }

    /**
     * Returns the current angular velocity.
     *
     * @return float
     */
    public function getAngularVelocity(): float
    {
        return $this->angularVelocity;
    }

    /**
     * Sets the current angular velocity.
     *
     * @param float $angularVelocity
     * @return void
     */
    public function setAngularVelocity(float $angularVelocity): void
    {
        $this->angularVelocity = $angularVelocity;
    }

    /**
     * Returns the rigidbody mass.
     *
     * @return float
     */
    public function getMass(): float
    {
        return $this->mass;
    }

    /**
     * Sets the rigidbody mass.
     *
     * @param float $mass
     * @return void
     */
    public function setMass(float $mass): void
    {
        $this->mass = max(0.0001, $mass);
    }

    /**
     * Sets linear drag.
     *
     * @param float $drag
     * @return void
     */
    public function setDrag(float $drag): void
    {
        $this->drag = max(0.0, $drag);
    }

    /**
     * Sets angular drag.
     *
     * @param float $drag
     * @return void
     */
    public function setAngularDrag(float $drag): void
    {
        $this->angularDrag = max(0.0, $drag);
    }

    /**
     * Toggles gravity for this rigidbody.
     *
     * @param bool $useGravity
     * @return void
     */
    public function setUseGravity(bool $useGravity): void
    {
        $this->useGravity = $useGravity;
    }

    /**
     * Sets the physics material from metadata or a concrete material.
     *
     * @param mixed $material
     * @return void
     */
    public function setMaterial(mixed $material): void
    {
        parent::setMaterial($material);
    }

    /**
     * Integrates forces and drag into the current velocity state.
     *
     * @param float $deltaTime
     * @return void
     */
    private function integrateForces(float $deltaTime): void
    {
        $inverseMass = 1.0 / $this->mass;
        $forceX = $this->accumulatedForceX;
        $forceY = $this->accumulatedForceY;

        if ($this->useGravity && $this->physics) {
            $forceY += $this->physics->getGravity() * $this->mass;
        }

        $this->velocityX += ($forceX * $inverseMass) * $deltaTime;
        $this->velocityY += ($forceY * $inverseMass) * $deltaTime;
        $this->angularVelocity += ($this->accumulatedTorque * $inverseMass) * $deltaTime;

        $linearDragFactor = max(0.0, 1.0 - ($this->drag * $deltaTime));
        $angularDragFactor = max(0.0, 1.0 - ($this->angularDrag * $deltaTime));

        $this->velocityX *= $linearDragFactor;
        $this->velocityY *= $linearDragFactor;
        $this->angularVelocity *= $angularDragFactor;

        $this->velocityX = $this->sanitizeVelocity($this->velocityX);
        $this->velocityY = $this->sanitizeVelocity($this->velocityY);
        $this->angularVelocity = $this->sanitizeVelocity($this->angularVelocity);
    }

    /**
     * Applies collision-constrained translation to the target float position.
     *
     * @param float $targetPositionX
     * @param float $targetPositionY
     * @param float $deltaTime
     * @return array{0: float, 1: float}
     */
    private function applyLinearMotion(float $targetPositionX, float $targetPositionY, float $deltaTime): array
    {
        $resolvedPositionX = $this->applyAxisMotion('x', $targetPositionX);
        $this->simulatedPositionX = $resolvedPositionX;

        if ($this->queuedPositionTarget === null) {
            $targetPositionY = $this->simulatedPositionY + ($this->velocityY * $deltaTime);
        }

        $resolvedPositionY = $this->applyAxisMotion('y', $targetPositionY);

        return [$resolvedPositionX, $resolvedPositionY];
    }

    /**
     * Applies integer cell-by-cell movement along a single axis.
     *
     * @param string $axis
     * @param float $targetPosition
     * @return float
     */
    private function applyAxisMotion(string $axis, float $targetPosition): float
    {
        $currentGrid = $axis === 'x'
            ? $this->getTransform()->getPosition()->getX()
            : $this->getTransform()->getPosition()->getY();
        $desiredGrid = $this->gridCoordinateFromFloat($targetPosition);
        $remainingSteps = $desiredGrid - $currentGrid;

        if ($remainingSteps === 0) {
            return $targetPosition;
        }

        $direction = $remainingSteps < 0 ? -1 : 1;

        while ($remainingSteps !== 0) {
            $motion = $axis === 'x'
                ? new Vector2($direction, 0)
                : new Vector2(0, $direction);
            $collisions = $this->physics?->checkCollisions($this, $motion) ?? [];
            $this->dispatchCollisions($collisions);
            $blockingCollision = $this->getFirstBlockingCollision($collisions);

            if ($blockingCollision) {
                $this->applyCollisionResponse($axis, $blockingCollision);
                return (float)$currentGrid;
            }

            $this->getTransform()->translate($motion);
            $currentGrid += $direction;
            $remainingSteps -= $direction;
        }

        return $targetPosition;
    }

    /**
     * Applies a simple material-based bounce and tangential friction response.
     *
     * @param string $axis
     * @param CollisionInterface $collision
     * @return void
     */
    private function applyCollisionResponse(string $axis, CollisionInterface $collision): void
    {
        $otherCollider = $collision->getContact(0)?->getOtherCollider();
        $otherMaterial = $otherCollider && method_exists($otherCollider, 'getMaterial')
            ? $otherCollider->getMaterial()
            : new PhysicsMaterial();
        $combinedMaterial = $this->getMaterial()->combine($otherMaterial);

        if ($axis === 'x') {
            $this->velocityX = $this->sanitizeVelocity($combinedMaterial->applyBounce($this->velocityX));
            $this->velocityY = $this->sanitizeVelocity($combinedMaterial->applyFriction($this->velocityY));
            return;
        }

        $this->velocityY = $this->sanitizeVelocity($combinedMaterial->applyBounce($this->velocityY));
        $this->velocityX = $this->sanitizeVelocity($combinedMaterial->applyFriction($this->velocityX));
    }

    /**
     * Returns the first collision that should block rigidbody movement.
     *
     * @param array<CollisionInterface> $collisions
     * @return CollisionInterface|null
     */
    private function getFirstBlockingCollision(array $collisions): ?CollisionInterface
    {
        foreach ($collisions as $collision) {
            if (!($collision->getContact(0)?->getOtherCollider()?->isTrigger() ?? false)) {
                return $collision;
            }
        }

        return null;
    }

    /**
     * Broadcasts enter/stay collision events for the unique collisions detected during this simulation step.
     *
     * @param array<CollisionInterface> $collisions
     * @return void
     */
    private function dispatchCollisions(array $collisions): void
    {
        foreach ($collisions as $collision) {
            $collisionKey = $this->getCollisionKey($collision);

            if ($collisionKey === null || isset($this->currentCollisions[$collisionKey])) {
                continue;
            }

            $this->currentCollisions[$collisionKey] = $collision;
            $methodName = isset($this->previousCollisions[$collisionKey])
                ? 'onCollisionStay'
                : 'onCollisionEnter';

            $this->broadcastCollisionEvent($methodName, $collision);
        }
    }

    /**
     * Broadcasts collision exit events for any collision that ended this simulation step.
     *
     * @return void
     */
    private function dispatchExitedCollisions(): void
    {
        foreach ($this->previousCollisions as $collisionKey => $collision) {
            if (isset($this->currentCollisions[$collisionKey])) {
                continue;
            }

            $this->broadcastCollisionEvent('onCollisionExit', $collision);
        }
    }

    /**
     * Broadcasts the collision event to this rigidbody and a mirrored collision event to the other collider.
     *
     * @param string $methodName
     * @param CollisionInterface $collision
     * @return void
     */
    private function broadcastCollisionEvent(string $methodName, CollisionInterface $collision): void
    {
        $contact = $collision->getContact(0);
        $otherCollider = $contact?->getOtherCollider();

        if ($contact === null) {
            return;
        }

        $this->getGameObject()->broadcast($methodName, ['collision' => $collision]);

        if ($otherCollider === null) {
            return;
        }

        $mirroredCollision = new Collision(
            $this,
            [
                new ContactPoint(
                    Vector2::getClone($contact->getPoint()),
                    $otherCollider,
                    $this,
                ),
            ],
        );

        $otherCollider->getGameObject()->broadcast($methodName, ['collision' => $mirroredCollision]);
    }

    /**
     * Returns a stable key for the other collider participating in a collision.
     *
     * @param CollisionInterface $collision
     * @return string|null
     */
    private function getCollisionKey(CollisionInterface $collision): ?string
    {
        $contact = $collision->getContact(0);
        $otherCollider = $contact?->getOtherCollider();

        if ($otherCollider !== null) {
            return $otherCollider->getHash();
        }

        if ($collision instanceof EnvironmentCollision) {
            $point = $contact?->getPoint();

            if ($point !== null) {
                return 'environment:' . $point->getX() . ':' . $point->getY();
            }
        }

        return null;
    }

    /**
     * Applies the resolved rotation to the transform.
     *
     * @param float $targetRotationX
     * @param float $targetRotationY
     * @return void
     */
    private function applyRotationalMotion(float $targetRotationX, float $targetRotationY): void
    {
        $this->simulatedRotationX = $targetRotationX;
        $this->simulatedRotationY = $targetRotationY;

        $this->getTransform()->setRotation(
            new Vector2(
                (int)round($targetRotationX),
                (int)round($targetRotationY)
            )
        );
    }

    /**
     * Sync internal float state from the current transform when needed.
     *
     * @param bool $force
     * @return void
     */
    private function syncSimulationState(bool $force = false): void
    {
        $position = $this->getTransform()->getPosition();
        $rotation = $this->getTransform()->getRotation();

        if (
            $force ||
            !$this->simulationStateInitialized ||
            $this->gridCoordinateFromFloat($this->simulatedPositionX) !== $position->getX() ||
            $this->gridCoordinateFromFloat($this->simulatedPositionY) !== $position->getY() ||
            (int)round($this->simulatedRotationX) !== $rotation->getX() ||
            (int)round($this->simulatedRotationY) !== $rotation->getY()
        ) {
            $this->simulatedPositionX = $position->getX();
            $this->simulatedPositionY = $position->getY();
            $this->simulatedRotationX = $rotation->getX();
            $this->simulatedRotationY = $rotation->getY();
            $this->simulationStateInitialized = true;
        }
    }

    /**
     * Restores the transform position to the last physics-simulated cell.
     *
     * This keeps queued rigidbody movement constrained even if a caller mutated the live transform position vector.
     *
     * @return void
     */
    private function restoreTransformPositionFromSimulationState(): void
    {
        if (!$this->simulationStateInitialized) {
            $this->syncSimulationState(force: true);
            return;
        }

        $this->getTransform()->setPosition(
            new Vector2(
                $this->gridCoordinateFromFloat($this->simulatedPositionX),
                $this->gridCoordinateFromFloat($this->simulatedPositionY),
            )
        );
    }

    /**
     * Clears one-shot movement and force accumulators after a simulation step.
     *
     * @return void
     */
    private function clearQueuedMovement(): void
    {
        $this->queuedPositionTarget = null;
        $this->queuedRotationTarget = null;
        $this->accumulatedForceX = 0.0;
        $this->accumulatedForceY = 0.0;
        $this->accumulatedTorque = 0.0;
    }

    /**
     * Resolves the current fixed-step delta time.
     *
     * @return float
     */
    private function resolveDeltaTime(): float
    {
        $deltaTime = Time::getDeltaTime();

        if ($deltaTime <= self::VELOCITY_EPSILON) {
            return self::DEFAULT_FIXED_DELTA_TIME;
        }

        return $deltaTime;
    }

    /**
     * Rotates local-space vector components into world space using the current x rotation as the 2D angle.
     *
     * @param float $x
     * @param float $y
     * @return array{0: float, 1: float}
     */
    private function rotateVectorToWorld(float $x, float $y): array
    {
        $this->syncSimulationState();

        $angleInRadians = deg2rad($this->simulatedRotationX);
        $cosine = cos($angleInRadians);
        $sine = sin($angleInRadians);

        return [
            ($x * $cosine) - ($y * $sine),
            ($x * $sine) + ($y * $cosine),
        ];
    }

    /**
     * Adds raw float force components to the accumulator.
     *
     * @param float $x
     * @param float $y
     * @return void
     */
    private function addForceComponents(float $x, float $y): void
    {
        $this->accumulatedForceX += $x;
        $this->accumulatedForceY += $y;
    }

    /**
     * Converts a float simulation coordinate to the corresponding grid cell without losing sub-cell accumulation.
     *
     * @param float $value
     * @return int
     */
    private function gridCoordinateFromFloat(float $value): int
    {
        return $value >= 0
            ? (int)floor($value)
            : (int)ceil($value);
    }

    /**
     * Zeroes tiny velocities so materials and drag settle cleanly.
     *
     * @param float $velocity
     * @return float
     */
    private function sanitizeVelocity(float $velocity): float
    {
        return abs($velocity) < self::VELOCITY_EPSILON ? 0.0 : $velocity;
    }
}

<?php

use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\Coroutines\WaitForFixedUpdate;
use Sendama\Engine\Core\Coroutines\WaitForSeconds;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Time;

if (!class_exists(CoroutineProbe::class)) {
  class CoroutineProbe extends Behaviour
  {
    public array $events = [];
    public bool $startNullCoroutineOnUpdate = false;
    private bool $startedNullCoroutine = false;

    public function onUpdate(): void
    {
      if ($this->startNullCoroutineOnUpdate && !$this->startedNullCoroutine) {
        $this->startedNullCoroutine = true;
        $this->startCoroutine($this->waitOneFrame());
      }
    }

    public function waitWithDelay(float $seconds): Generator
    {
      $this->events[] = 'started';
      yield new WaitForSeconds($seconds);
      $this->events[] = 'delay-complete';
      yield null;
      $this->events[] = 'next-update';
    }

    public function waitForFixedStep(): Generator
    {
      $this->events[] = 'started';
      yield new WaitForFixedUpdate();
      $this->events[] = 'fixed-complete';
    }

    public function waitOneFrame(): Generator
    {
      $this->events[] = 'started';
      yield null;
      $this->events[] = 'next-update';
    }
  }
}

beforeEach(function () {
  setCoroutineTestTime(0.0, 0.0);
});

it('resumes wait-for-seconds coroutines after enough update time has elapsed', function () {
  $gameObject = new GameObject('Coroutine Probe');
  $probe = $gameObject->addComponent(CoroutineProbe::class);

  $probe->startCoroutine($probe->waitWithDelay(1.0));

  expect($probe->events)->toBe(['started']);

  setCoroutineTestTime(0.0);
  $gameObject->update();
  expect($probe->events)->toBe(['started']);

  setCoroutineTestTime(0.5);
  $gameObject->update();
  expect($probe->events)->toBe(['started']);

  setCoroutineTestTime(1.0);
  $gameObject->update();
  expect($probe->events)->toBe(['started', 'delay-complete']);

  setCoroutineTestTime(1.1);
  $gameObject->update();
  expect($probe->events)->toBe(['started', 'delay-complete', 'next-update']);
});

it('resumes wait-for-fixed-update coroutines on the next fixed step', function () {
  $gameObject = new GameObject('Coroutine Probe');
  $probe = $gameObject->addComponent(CoroutineProbe::class);

  $probe->startCoroutine($probe->waitForFixedStep());

  $gameObject->update();

  expect($probe->events)->toBe(['started']);

  $gameObject->fixedUpdate();

  expect($probe->events)->toBe(['started', 'fixed-complete']);
});

it('does not resume a newly started yield-null coroutine in the same update cycle', function () {
  $gameObject = new GameObject('Coroutine Probe');
  $probe = $gameObject->addComponent(CoroutineProbe::class);
  $probe->startNullCoroutineOnUpdate = true;

  $gameObject->update();

  expect($probe->events)->toBe(['started']);

  $gameObject->update();

  expect($probe->events)->toBe(['started', 'next-update']);
});

it('pauses coroutine progression while the behaviour is suspended', function () {
  $gameObject = new GameObject('Coroutine Probe');
  $probe = $gameObject->addComponent(CoroutineProbe::class);

  $probe->startCoroutine($probe->waitOneFrame());
  $probe->suspend();

  $gameObject->update();
  expect($probe->events)->toBe(['started']);

  $probe->resume();
  $gameObject->update();

  expect($probe->events)->toBe(['started', 'next-update']);
});

function setCoroutineTestTime(float $time, ?float $deltaTime = null): void
{
  static $lastTime = 0.0;

  if ($deltaTime === null) {
    $deltaTime = max(0.0, $time - $lastTime);
  }

  $lastTime = $time;

  setCoroutineTestStatic(Time::class, 'time', $time);
  setCoroutineTestStatic(Time::class, 'deltaTime', $deltaTime);
}

function setCoroutineTestStatic(string $className, string $propertyName, mixed $value): void
{
  $property = new ReflectionProperty($className, $propertyName);
  $property->setValue(null, $value);
}

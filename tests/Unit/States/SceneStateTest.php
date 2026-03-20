<?php

use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Game;
use Sendama\Engine\Interfaces\GameStateInterface;
use Sendama\Engine\IO\InputManager;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\Mocks\MockBehavior;
use Sendama\Engine\States\GameStateContext;
use Sendama\Engine\States\SceneState;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\UIManager;

beforeEach(function () {
  resetSceneStateSingleton(SceneManager::class, 'instance');
  resetSceneStateSingleton(EventManager::class, 'instance');
  resetSceneStateSingleton(ModalManager::class, 'instance');
  resetSceneStateSingleton(NotificationsManager::class, 'instance');
  resetSceneStateSingleton(UIManager::class, 'instance');
  setSceneStateInputManagerState('previousKeyPress', '');
  setSceneStateInputManagerState('keyPress', '');
});

it('does not advance the active scene on the frame pause is pressed', function () {
  $sceneManager = SceneManager::getInstance();
  $sceneManager->loadSettings([
    'screen_width' => 80,
    'screen_height' => 25,
  ]);

  $scene = new class('Playfield') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $player = new GameObject('Player');
  /** @var MockBehavior $behavior */
  $behavior = $player->addComponent(MockBehavior::class);
  $scene->add($player);
  $sceneManager->addScene($scene);

  ob_start();
  $sceneManager->loadScene('Playfield');
  ob_end_clean();

  $pausedState = new class implements GameStateInterface {
    public function enter(GameStateContext $context): void
    {
      // Do nothing.
    }

    public function exit(GameStateContext $context): void
    {
      // Do nothing.
    }

    public function update(): void
    {
      // Do nothing.
    }

    public function render(): void
    {
      // Do nothing.
    }

    public function suspend(): void
    {
      // Do nothing.
    }

    public function resume(): void
    {
      // Do nothing.
    }
  };

  $game = new SceneStateTestGame([
    SettingsKey::PAUSE_KEY->value => 'escape',
  ]);
  $game->registerState('paused', $pausedState);

  $state = new SceneState(new GameStateContext(
    $game,
    $sceneManager,
    EventManager::getInstance(),
    ModalManager::getInstance(),
    NotificationsManager::getInstance(),
    UIManager::getInstance(),
  ));

  $game->registerState('scene', $state);
  $game->setCurrentState($state);

  setSceneStateInputManagerState('previousKeyPress', '');
  setSceneStateInputManagerState('keyPress', "\033");

  $state->update();

  expect($game->getCurrentState())->toBe($pausedState)
    ->and($behavior->fixedUpdateCount)->toBe(0)
    ->and($behavior->updateCount)->toBe(0);
});

function resetSceneStateSingleton(string $className, string $property): void
{
  $reflection = new ReflectionClass($className);
  $reflection->getProperty($property)->setValue(null, null);
}

function setSceneStateInputManagerState(string $property, string $value): void
{
  $reflection = new ReflectionClass(InputManager::class);
  $reflection->getProperty($property)->setValue(null, $value);
}

final class SceneStateTestGame extends Game
{
  /** @var array<string, GameStateInterface> */
  private array $states = [];
  private ?GameStateInterface $currentState = null;

  public function __construct(private array $testSettings = [])
  {
    // Intentionally skip the parent bootstrapping for unit tests.
  }

  public function __destruct()
  {
    // No-op for tests.
  }

  public function registerState(string $name, GameStateInterface $state): void
  {
    $this->states[$name] = $state;
  }

  public function setCurrentState(GameStateInterface $state): void
  {
    $this->currentState = $state;
  }

  public function getCurrentState(): ?GameStateInterface
  {
    return $this->currentState;
  }

  public function getSettings(string|SettingsKey|null $key = null): mixed
  {
    $key = match (true) {
      $key === null => null,
      is_string($key) => $key,
      default => $key->value,
    };

    if ($key === null) {
      return $this->testSettings;
    }

    return $this->testSettings[$key] ?? null;
  }

  public function getState(string $stateName): ?GameStateInterface
  {
    return $this->states[$stateName] ?? null;
  }

  public function setState(GameStateInterface $state): void
  {
    $this->currentState = $state;
  }
}

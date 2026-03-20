<?php

use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Game;
use Sendama\Engine\Interfaces\GameStateInterface;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\InputManager;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\States\GameStateContext;
use Sendama\Engine\States\PausedState;
use Sendama\Engine\UI\Label\Label;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\Menus\Menu;
use Sendama\Engine\UI\UIManager;

beforeEach(function () {
  resetPausedStateSingleton(SceneManager::class, 'instance');
  resetPausedStateSingleton(EventManager::class, 'instance');
  resetPausedStateSingleton(ModalManager::class, 'instance');
  resetPausedStateSingleton(NotificationsManager::class, 'instance');
  resetPausedStateSingleton(UIManager::class, 'instance');
  setPausedStateInputManagerState('previousKeyPress', '');
  setPausedStateInputManagerState('keyPress', '');
});

it('centers the default pause text over the occupied scene bounds instead of the full logical canvas', function () {
  $sceneManager = SceneManager::getInstance();
  $sceneManager->loadSettings([
    'screen_width' => 160,
    'screen_height' => 40,
  ]);

  $scene = new class('Playfield') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $scene->add(new Label($scene, 'Collected Label', new Vector2(65, 27), new Vector2(15, 1)));
  $sceneManager->addScene($scene);

  ob_start();
  $sceneManager->loadScene('Playfield');
  ob_end_clean();

  $scene->getWorldSpace()->set(0, 0, 'x');
  $scene->getWorldSpace()->set(79, 24, 'x');

  Console::refreshLayout(
    160,
    40,
    new Rect(new Vector2(1, 1), new Vector2(160, 40)),
    clearWhenChanged: false
  );

  $state = new PausedState(new GameStateContext(
    new TestGame([
      SettingsKey::SCREEN_WIDTH->value => 160,
      SettingsKey::SCREEN_HEIGHT->value => 40,
    ]),
    $sceneManager,
    EventManager::getInstance(),
    ModalManager::getInstance(),
    NotificationsManager::getInstance(),
    UIManager::getInstance(),
  ));

  ob_start();
  $state->render();
  $output = ob_get_clean();

  expect($output)->toContain("\033[14;38HPAUSED");
});

it('does not update the pause menu on the frame resume is requested', function () {
  $sceneState = new class implements GameStateInterface {
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

  $game = new TestGame([
    SettingsKey::PAUSE_KEY->value => 'escape',
  ]);
  $game->registerState('scene', $sceneState);

  $state = new PausedState(new GameStateContext(
    $game,
    SceneManager::getInstance(),
    EventManager::getInstance(),
    ModalManager::getInstance(),
    NotificationsManager::getInstance(),
    UIManager::getInstance(),
  ));

  $game->registerState('paused', $state);
  $game->setCurrentState($state);

  $menu = new class('', '') extends Menu {
    public int $updateCount = 0;

    public function update(): void
    {
      $this->updateCount++;
    }
  };

  $reflection = new ReflectionClass($state);
  $reflection->getProperty('menu')->setValue($state, $menu);

  setPausedStateInputManagerState('previousKeyPress', '');
  setPausedStateInputManagerState('keyPress', "\033");

  ob_start();
  $state->update();
  ob_end_clean();

  expect($game->getCurrentState())->toBe($sceneState)
    ->and($menu->updateCount)->toBe(0);
});

function resetPausedStateSingleton(string $className, string $property): void
{
  $reflection = new \ReflectionClass($className);
  $reflection->getProperty($property)->setValue(null, null);
}

function setPausedStateInputManagerState(string $property, string $value): void
{
  $reflection = new ReflectionClass(InputManager::class);
  $reflection->getProperty($property)->setValue(null, $value);
}

final class TestGame extends Game
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

  public function getState(string $stateName): ?GameStateInterface
  {
    return $this->states[$stateName] ?? null;
  }

  public function setState(GameStateInterface $state): void
  {
    $this->currentState = $state;
  }
}

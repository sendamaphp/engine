<?php

use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Game;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\States\GameStateContext;
use Sendama\Engine\States\PausedState;
use Sendama\Engine\UI\Label\Label;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\UIManager;

beforeEach(function () {
  resetPausedStateSingleton(SceneManager::class, 'instance');
  resetPausedStateSingleton(EventManager::class, 'instance');
  resetPausedStateSingleton(ModalManager::class, 'instance');
  resetPausedStateSingleton(NotificationsManager::class, 'instance');
  resetPausedStateSingleton(UIManager::class, 'instance');
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

function resetPausedStateSingleton(string $className, string $property): void
{
  $reflection = new \ReflectionClass($className);
  $reflection->getProperty($property)->setValue(null, null);
}

final class TestGame extends Game
{
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
}

<?php

namespace Sendama\Engine\States;

use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Game;
use Sendama\Engine\Interfaces\GameStateInterface;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\UIManager;

/**
 * Class GameState. Represents a state of the game.
 */
abstract class GameState implements GameStateInterface
{
    protected Game $game;
    protected SceneManager $sceneManager;
    protected EventManager $eventManager;
    protected ModalManager $modalManager;
    protected NotificationsManager $notificationsManager;
    protected UIManager $UIManager;

    /**
     * @param GameStateContext $context
     */
    public final function __construct(GameStateContext $context)
    {
        $this->game = $context->game;
        $this->sceneManager = $context->sceneManager;
        $this->eventManager = $context->eventManager;
        $this->modalManager = $context->modalManager;
        $this->notificationsManager = $context->notificationsManager;
    }

    /**
     * @inheritDoc
     */
    public function enter(GameStateContext $context): void
    {
        $this->game = $context->game;
        $this->sceneManager = $context->sceneManager;
        $this->eventManager = $context->eventManager;
        $this->modalManager = $context->modalManager;
        $this->notificationsManager = $context->notificationsManager;

        // Do nothing.
    }

    /**
     * @inheritDoc
     */
    public function exit(GameStateContext $context): void
    {
        $this->game = $context->game;
        $this->sceneManager = $context->sceneManager;
        $this->eventManager = $context->eventManager;
        $this->modalManager = $context->modalManager;
        $this->notificationsManager = $context->notificationsManager;

        // Do nothing.
    }
}
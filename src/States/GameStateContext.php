<?php

namespace Sendama\Engine\States;

use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Game;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\UIManager;

/**
 * GameStateContext
 */
readonly class GameStateContext
{
    /**
     * @param Game $game
     * @param SceneManager $sceneManager
     * @param EventManager $eventManager
     * @param ModalManager $modalManager
     * @param NotificationsManager $notificationsManager
     * @param UIManager $UIManager
     */
    public function __construct(
        public Game                 $game,
        public SceneManager         $sceneManager,
        public EventManager         $eventManager,
        public ModalManager         $modalManager,
        public NotificationsManager $notificationsManager,
        public UIManager            $UIManager,
    )
    {
    }
}
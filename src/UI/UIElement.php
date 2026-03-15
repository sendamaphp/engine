<?php

namespace Sendama\Engine\UI;

use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\UI\Interfaces\UIElementInterface;

/**
 * The abstract UI element class.
 *
 * @package Sendama\Engine\UI
 */
abstract class UIElement implements UIElementInterface
{
    /**
     * Whether the UI element is active.
     *
     * @var bool
     */
    protected bool $active = true;

    /**
     * Constructs a UI element.
     *
     * @param SceneInterface $scene The scene.
     * @param string $name The name of the UI element.
     * @param Vector2 $position The position of the UI element.
     * @param Vector2 $size The size of the UI element.
     */
    public function __construct(
        protected SceneInterface $scene,
        protected string         $name,
        protected Vector2        $position = new Vector2(0, 0),
        protected Vector2        $size = new Vector2(0, 0),
        protected string         $tag = '',
    )
    {
        $this->awake();
    }

    /**
     * @inheritDoc
     */
    public function awake(): void
    {
        // Do nothing.
    }

    /**
     * @inheritDoc
     */
    public static function find(string $uiElementName): ?UIElementInterface
    {
        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getUIElements() as $element) {
                if ($element->getName() === $uiElementName) {
                    return $element;
                }
            }
        }

        return null;
    }

    /**
     * Finds a UI element by its tag.
     *
     * @param string $uiElementTagName The tag of the UI element.
     * @return self|null The UI element if found, null otherwise.
     */
    public static function findByTag(string $uiElementTagName): ?UIElementInterface
    {
        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getUIElements() as $element) {
                if ($element->getTag() === $uiElementTagName) {
                    return $element;
                }
            }
        }

        return null;
    }

    /**
     * Finds all UI elements by their tag
     *
     * @param string $uiElementTagName The tag of the UI element.
     * @return UIElementInterface[] The UI elements if found, an empty array otherwise.
     */
    public static function findAllByTag(string $uiElementTagName): array
    {
        $elements = [];

        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getUIElements() as $element) {
                if ($element->getTag() === $uiElementTagName) {
                    $elements[] = $element;
                }
            }
        }

        return $elements;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public static function findAll(string $uiElementName): array
    {
        $elements = [];

        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getUIElements() as $element) {
                if ($element->getName() === $uiElementName) {
                    $elements[] = $element;
                }
            }
        }

        return $elements;
    }

    /**
     * @inheritDoc
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * @inheritDoc
     */
    public function setTag(string $tag): void
    {
        $this->tag = $tag;
    }

    /**
     * @inheritDoc
     */
    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * @inheritDoc
     */
    public function resume(): void
    {
        if ($this->shouldRenderWithinScene()) {
            $this->render();
        }
    }

    /**
     * Checks whether the element should write to the console right now.
     *
     * @return bool
     */
    protected function shouldRenderWithinScene(): bool
    {
        return $this->isActive() && $this->scene->isStarted();
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @inheritDoc
     */
    public function suspend(): void
    {
        if ($this->shouldRenderWithinScene()) {
            $this->erase();
        }
    }

    /**
     * @inheritDoc
     */
    public function stop(): void
    {
        if ($this->shouldRenderWithinScene()) {
            $this->erase();
        }
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): Vector2
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function setPosition(Vector2 $position): void
    {
        $this->position = $position;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): Vector2
    {
        return $this->size;
    }

    /**
     * @inheritDoc
     */
    public function setSize(Vector2 $size): void
    {
        $this->size = $size;
    }
}

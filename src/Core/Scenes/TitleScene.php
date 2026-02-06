<?php

namespace Sendama\Engine\Core\Scenes;

use Amasiye\Figlet\FontName;
use Exception;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Enumerations\KeyCode;
use Sendama\Engine\UI\Menus\Interfaces\MenuItemInterface;
use Sendama\Engine\UI\Menus\Menu;
use Sendama\Engine\UI\Menus\MenuItems\MenuItem;
use Sendama\Engine\UI\Text\Text;
use Sendama\Engine\UI\Windows\Interfaces\BorderPackInterface;

/**
 * Class TitleScene. Represents a title scene.
 *
 * @package Sendama\Engine\Core\Scenes
 */
class TitleScene extends AbstractScene
{
    const int TOP_MARGIN_OFFSET = 4;
    /**
     * @var Menu $menu
     */
    protected Menu $menu;
    /**
     * @var Text $titleText
     */
    protected Text $titleText;
    /**
     * @var int|null
     */
    protected ?int $screenWidth = null;
    /**
     * @var int|null
     */
    protected ?int $screenHeight = null;
    /**
     * The width of the menu.
     *
     * @var int $menuWidth
     */
    protected int $menuWidth = 20;
    /**
     * The height of the menu.
     *
     * @var int $menuHeight
     */
    protected int $menuHeight = 8;
    /**
     * The scene manager.
     *
     * @var SceneManager $sceneManager
     */
    protected SceneManager $sceneManager;
    /**
     * The title of the game.
     *
     * @var string $title
     */
    protected string $title = '';
    /**
     * The left margin of the title.
     *
     * @var int $titleLeftMargin
     */
    protected int $titleLeftMargin = 4;
    /**
     * The top margin of the title.
     *
     * @var int $titleTopMargin
     */
    protected int $titleTopMargin = 4;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function awake(): void
    {
        $this->sceneManager = SceneManager::getInstance();
        $gameName = getGameName() ?? $this->name;

        $this->titleText = new Text(scene: $this, name: $gameName, position: new Vector2(0, $this->titleTopMargin), size: new Vector2(DEFAULT_SCREEN_WIDTH, 5));
        $this->titleText->setFontName(FontName::BIG->value);
        $this->setTitleText($gameName);

        if (is_array($gameName)) {
            $gameName = $_ENV['GAME_NAME'] ?? $this->name;
        }

        $this->menu = new Menu(title: $gameName, description: 'q:quit', dimensions: new Rect(new Vector2($this->getMenuLeftMargin(), $this->getMenuTopMargin()), new Vector2($this->menuWidth, $this->menuHeight)), cancelKey: [KeyCode::Q, KeyCode::q], onCancel: fn() => quitGame());
        $this->menu->addItem(new MenuItem(label: 'New Game', description: 'Start a new game', icon: '🎮', callback: function () {
            loadScene(1);
        }));
        $this->menu->addItem(new MenuItem(label: 'Quit', description: 'Quit the game', icon: '🚪', callback: function () {
            quitGame();
        }));

        $this->add($this->titleText);
        $this->add($this->menu);
    }

    /**
     * Returns the left margin of the menu based on the screen width and the menu width.
     * This is used to center the menu on the screen.
     *
     * @return int The left margin of the menu.
     * @throws Exception
     */
    private function getMenuLeftMargin(): int
    {
        $screenWidth = $this->screenWidth ?? get_screen_width();
        return (int)round($screenWidth / 2) - (int)round($this->menuWidth / 2);
    }

    /**
     * @return int
     */
    private function getMenuTopMargin(): int
    {
        return ($this->titleTopMargin + $this->titleText->getHeight() + 1);
    }

    /**
     * @param string $text
     * @return $this
     * @throws Exception
     */
    public function setTitleText(string $text): self
    {
        $this->titleText->setText($text);
        $this->titleLeftMargin = round((get_screen_width() / 2) - ($this->titleText->getWidth() / 2));
        $this->titleTopMargin = self::TOP_MARGIN_OFFSET;
        $this->titleText->setPosition(new Vector2(round($this->titleLeftMargin), round($this->titleTopMargin)));

        return $this;
    }

    /**
     * Sets the font name of the title text.
     *
     * @param FontName|string $fontName The font name of the title text.
     * @return $this
     * @throws Exception
     */
    public function setTitleFont(FontName|string $fontName): self
    {
        $this->titleText->setFontName($fontName instanceof FontName ? $fontName->value : $fontName);
        return $this;
    }

    /**
     * Returns the title of the menu.
     *
     * @return string The title of the menu.
     */
    public function getMenuTitle(): string
    {
        return $this->menu->getTitle();
    }

    public function getTitle(): string
    {
        return $this->titleText->getText();
    }

    /**
     * Set the title of the menu.
     * @param string $title The title of the menu.
     * @return void
     */
    public function setMenuTitle(string $title): void
    {
        $this->menu->setTitle($title);
    }

    /**
     * Sets the default border pack that will be used to render the menu borders.
     *
     * @param BorderPackInterface $borderPack The border pack to use.
     * @return $this
     */
    public function setBorderPack(BorderPackInterface $borderPack): self
    {
        $this->menu->setBorderPack($borderPack);
        return $this;
    }

    /**
     * Sets the index of the new game scene.
     *
     * @param int $newGameSceneIndex The index of the new game scene.
     * @return TitleScene $this
     */
    public function setNewGameSceneIndex(int $newGameSceneIndex): self
    {
        $this->menu->getItemByIndex(0)->setCallback(function () use ($newGameSceneIndex) {
            loadScene(max($newGameSceneIndex, 1));
        });

        return $this;
    }

    /**
     * Sets the index of the new game scene by the scene name.
     *
     * @param string $newGameSceneName The name of the new game scene.
     * @return TitleScene $this
     */
    public function setNewGameSceneIndexBySceneName(string $newGameSceneName): self
    {
        $this->menu->getItemByIndex(0)->setCallback(function () use ($newGameSceneName) {
            loadScene($newGameSceneName);
        });

        return $this;
    }

    /**
     * Sets the screen dimensions.
     *
     * @param int|null $width The width of the screen.
     * @param int|null $height The height of the screen.
     * @return void
     */
    public function setScreenDimensions(?int $width = null, ?int $height = null): void
    {
        $this->screenWidth = $width;
        $this->screenHeight = $height;
    }

    /**
     * Adds menu items to the menu.
     *
     * @param MenuItemInterface ...$item The menu items to add.
     * @return $this
     */
    public function addMenuItems(MenuItemInterface ...$item): self
    {
        $lastItemIndex = $this->menu->getItems()->count() - 1;
        $quitItem = $this->menu->getItemByIndex($lastItemIndex);
        $this->menu->removeItemByIndex($lastItemIndex);

        foreach ($item as $menuItem) {
            $this->menu->addItem($menuItem);
        }

        $this->menu->addItem($quitItem);
        return $this;
    }
}
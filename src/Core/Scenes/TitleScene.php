<?php

namespace Sendama\Engine\Core\Scenes;

use Amasiye\Figlet\FontName;
use Exception;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Debug\Debug;
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
     * @var int|string
     */
    protected int|string $newGameSceneTarget = 1;

    protected int $uiHeight {
        get {
            $gap = 1;
            return $this->titleText->getHeight() + $gap + $this->menu->getItems()->count() + 2; // 1 for each border
        }
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function awake(): void
    {
        $gameName = getGameName() ?? $this->name;
        $screenWidth = $this->resolveScreenWidth();
        $titleTextHeight = 5;

        $this->titleText = new Text(
            scene: $this,
            name: $gameName,
            position: new Vector2(0, $this->titleTopMargin),
            size: new Vector2($screenWidth, $titleTextHeight)
        );
        $this->titleText->setFontName(FontName::BIG->value);
        $this->setTitleText($gameName);

        if (is_array($gameName)) {
            $gameName = $_ENV['GAME_NAME'] ?? $this->name;
        }

        $menuDimensions = new Rect(new Vector2($this->getMenuLeftMargin(), $this->getMenuTopMargin()), new Vector2($this->menuWidth, $this->menuHeight));
        $this->menu = new Menu(title: $gameName, description: 'q:quit', dimensions: $menuDimensions, cancelKey: [KeyCode::Q, KeyCode::q], onCancel: fn() => quitGame());
        $this->menu->addItem(new MenuItem(label: 'New Game', description: 'Start a new game', icon: '🎮'));
        $this->menu->addItem(new MenuItem(label: 'Quit', description: 'Quit the game', icon: '🚪', callback: function () {
            quitGame();
        }));
        $this->configureNewGameMenuItem($this->newGameSceneTarget);

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
        $screenWidth = $this->resolveScreenWidth();
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
        usleep(300000);
        // Ensure the Text has fresh dimensions — Figlet/font rendering or
        // console init may have completed after setText() ran. Refresh
        // dimensions if the helper exists so getWidth() is reliable here.
        try {
            if (method_exists($this->titleText, 'refreshDimensions')) {
                $this->titleText->refreshDimensions();
            }
        } catch (\Throwable $_) {
            // best-effort
        }

        $screenWidth = $this->resolveScreenWidth();
        $this->titleLeftMargin = (int)intdiv(max(0, $screenWidth - $this->titleText->getWidth()), 2);
        $this->titleTopMargin = self::TOP_MARGIN_OFFSET;
        $this->titleText->setPosition(new Vector2($this->titleLeftMargin, $this->titleTopMargin));
        Debug::log(var_export([
            'screenWidth' => $screenWidth,
            'titleText' => $this->titleText->getText(),
            'titleTextWidth' => $this->titleText->getWidth(),
            'titleLeftMargin' => $this->titleLeftMargin,
            'titleTopMargin' => $this->titleTopMargin,
            'titlePosition' => (string)$this->titleText->getPosition(),
            'timestamp' => time(),
        ], true));

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
        $this->newGameSceneTarget = $newGameSceneIndex;
        $this->configureNewGameMenuItem($newGameSceneIndex);

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
        $this->newGameSceneTarget = $newGameSceneName;
        $this->configureNewGameMenuItem($newGameSceneName);

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

    /**
     * Resolves the screen width even while the scene is still in awake() and local scene settings are empty.
     *
     * @return int
     * @throws Exception
     */
    private function resolveScreenWidth(): int
    {
        return $this->resolveDimension(
            $this->screenWidth,
            $this->sceneManager->getSettings('screen_width'),
            $this->getSettings('screen_width'),
            DEFAULT_SCREEN_WIDTH
        );
    }

    /**
     * @param mixed ...$values
     * @return int
     */
    private function resolveDimension(mixed ...$values): int
    {
        foreach ($values as $value) {
            if (is_int($value)) {
                return $value;
            }

            if (is_string($value) && is_numeric($value)) {
                return (int)$value;
            }
        }

        return DEFAULT_SCREEN_WIDTH;
    }

    /**
     * @param int|string $sceneTarget
     * @return void
     */
    private function configureNewGameMenuItem(int|string $sceneTarget): void
    {
        $newGameItem = $this->menu->getItemByIndex(0);

        if (!$newGameItem) {
            return;
        }

        if ($this->sceneManager->hasScene($sceneTarget)) {
            $newGameItem->setEnabled(true);
            $newGameItem->setDescription('Start a new game');
            $newGameItem->setCallback(function () use ($sceneTarget) {
                loadScene($sceneTarget);
            });
            $this->menu->setActiveItemByIndex(max($this->menu->getActiveItemIndex(), 0));
            $this->menu->updateWindowContent();
            return;
        }

        $newGameItem->setEnabled(false);
        $newGameItem->setDescription('No playable scene configured');
        $newGameItem->setCallback(null);

        Debug::warn(sprintf(
            'Title scene "%s" could not enable "New Game" because scene target "%s" is not available.',
            $this->getName(),
            (string)$sceneTarget
        ));

        $this->menu->setActiveItemByIndex(0);
        $this->menu->updateWindowContent();
    }
}

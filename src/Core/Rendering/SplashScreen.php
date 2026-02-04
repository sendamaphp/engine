<?php

namespace Sendama\Engine\Core\Rendering;

use Sendama\Engine\Debug\Debug;
use Exception;
use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Console\Cursor;
use Sendama\Engine\Util\Path;

final class SplashScreen
{
    public function __construct(
        private readonly Cursor $consoleCursor,
        private readonly array $settings
    )
    {
    }

    public function show(): void
    {
        try {
            Debug::info("Showing splash screen");
            Console::setSize(MAX_SCREEN_WIDTH, MAX_SCREEN_HEIGHT);

            // Check if a splash texture can be loaded
            if (!file_exists($this->getSettings('splash_texture'))) {
                Debug::warn("Splash screen texture not found: {$this->settings[SettingsKey::SPLASH_TEXTURE->value]}");
                $this->settings[SettingsKey::SPLASH_TEXTURE->value] = Path::join(Path::getVendorAssetsDirectory(), DEFAULT_SPLASH_TEXTURE_PATH);
            }

            Debug::info("Loading splash screen texture");
            $splashScreen = file_get_contents($this->getSettings('splash_texture'));
            $splashScreenRows = explode("\n", $splashScreen);
            $splashScreenWidth = 75;
            $splashScreenHeight = 25;
            $splashByLine = 'SendamaEngine ™';
            $splashScreenRows[] = sprintf("%s%s", str_repeat(' ', $splashScreenWidth - 12), "powered by");
            $splashScreenRows[] = sprintf("%s%s", str_repeat(' ', $splashScreenWidth - strlen($splashByLine)), $splashByLine);

            $leftMargin = (MAX_SCREEN_WIDTH / 2) - ($splashScreenWidth / 2);
            $topMargin = (MAX_SCREEN_HEIGHT / 2) - ($splashScreenHeight / 2);

            Debug::info("Rendering splash screen texture");
            foreach ($splashScreenRows as $rowIndex => $row) {
                $this->consoleCursor->moveTo((int)$leftMargin, (int)($topMargin + $rowIndex));
                Console::output()->write($row);
            }

            $duration = (int)($this->getSettings('splash_screen_duration') * 1000000);
            usleep($duration);

            Console::setSize($this->getSettings('screen_width'), $this->getSettings('screen_height'));
            Console::clear();

            Debug::info("Splash screen hidden");
        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    private function getSettings(string $key)
    {
        return $this->settings[$key] ?? null;
    }

    private function handleException(Exception $exception): void
    {
        Debug::error("An error occurred while displaying the splash screen: " . $exception->getMessage());
    }
}

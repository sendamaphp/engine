<?php

namespace Sendama\Engine\Core\Rendering;

use Sendama\Engine\Debug\Debug;
use Exception;
use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Console\Cursor;
use Sendama\Engine\Util\Path;
use Sendama\Engine\Util\Unicode;

final class SplashScreen
{
    public function __construct(
        private readonly Cursor $consoleCursor,
        private array $settings
    )
    {
    }

    public function show(): void
    {
        try {
            Debug::info("Showing splash screen");

            // Check if a splash texture can be loaded
            if (!file_exists($this->getSettings('splash_texture'))) {
                Debug::warn("Splash screen texture not found: {$this->settings[SettingsKey::SPLASH_TEXTURE->value]}");
                $this->settings[SettingsKey::SPLASH_TEXTURE->value] = Path::join(
                    Path::getVendorAssetsDirectory(),
                    basename(DEFAULT_SPLASH_TEXTURE_PATH)
                );
            }

            Debug::info("Loading splash screen texture");
            $splashScreen = file_get_contents($this->getSettings('splash_texture'));
            $splashScreenRows = explode("\n", $splashScreen);
            $splashByLine = 'SendamaEngine ™';
            $contentWidth = 75;
            $splashScreenRows[] = sprintf("%s%s", str_repeat(' ', $contentWidth - 12), "powered by");
            $splashScreenRows[] = sprintf("%s%s", str_repeat(' ', $contentWidth - Unicode::length($splashByLine)), $splashByLine);
            $splashScreenHeight = count($splashScreenRows);
            $splashScreenWidth = 0;

            foreach ($splashScreenRows as $row) {
                $splashScreenWidth = max($splashScreenWidth, Unicode::length($row));
            }

            $terminalSize = Console::getSize(force: true);

            $leftMargin = max(1, (int)floor(($terminalSize->getWidth() - $splashScreenWidth) / 2) + 1);
            $topMargin = max(1, (int)floor(($terminalSize->getHeight() - $splashScreenHeight) / 2) + 1);

            Debug::info("Rendering splash screen texture");
            foreach ($splashScreenRows as $rowIndex => $row) {
                $this->consoleCursor->moveTo((int)$leftMargin, (int)($topMargin + $rowIndex));
                Console::output()->write($row);
            }

            $duration = (int)($this->getSettings('splash_screen_duration') * 1000000);
            usleep($duration);

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

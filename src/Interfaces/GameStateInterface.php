<?php

namespace Sendama\Engine\Interfaces;

use Sendama\Engine\States\GameStateContext;

interface GameStateInterface
{
    /**
     * @param GameStateContext $context
     * @return void
     */
    public function enter(GameStateContext $context): void;

    /**
     * @param GameStateContext $context
     * @return void
     */
    public function exit(GameStateContext $context): void;

    /**
     * Updates the game state.
     *
     * @return void
     */
    public function update(): void;

    /**
     * Renders the game state.
     *
     * @return void
     */
    public function render(): void;

    /**
     * Suspends the game state.
     *
     * @return void
     */
    public function suspend(): void;

    /**
     * Resumes the game state.
     *
     * @return void
     */
    public function resume(): void;
}
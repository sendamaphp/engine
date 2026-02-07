<?php

use Sendama\Engine\Core\Texture;
use Sendama\Engine\IO\Enumerations\Color;
use Sendama\Engine\Util\Path;

describe('Texture', function () {
  beforeEach(function () {
    $this->workingDirectory = dirname(__DIR__, 2);
    $this->texturePath = Path::join($this->workingDirectory, 'Mocks/Textures/test.texture');
  });

  it ('can be created', function () {
    $texture = new Texture($this->texturePath);
    expect($texture)->toBeInstanceOf(Texture::class);
  });

  it('can manipulate the texture dimensions', function() {
    $texture = new Texture($this->texturePath);
    $width = 32;
    $height = 32;

    $texture->setWidth($width);
    $texture->setHeight($height);

    expect($texture->getWidth())
      ->toBe($width)
      ->and($texture->getHeight())
      ->toBe($height);
  });

  it('can control the texture coloer', function() {
    $texture = new Texture($this->texturePath);
    $color = Color::RED;

    $texture->setColor($color);

    expect($texture->getColor())
      ->toBe($color);
  });

  it('can manipulate texture pixels', function() {
    $texture = new Texture($this->texturePath);
    $x = 1;
    $y = 0;
    $expectedPixel = '<';

    expect($texture->getPixel($x, $y))
      ->toBe($expectedPixel);

    $newPixel = 'x';
    $texture->setPixel($x, $y, $newPixel);

    expect($texture->getPixel($x, $y))
      ->toBe($newPixel)
      ->not()->toBe($expectedPixel);
  });

  it('can be converted to a string', function() {
    $texture = new Texture($this->texturePath);
    $expectedString = file_get_contents($this->texturePath);

    expect(strval($texture))
      ->toBe($expectedString);
  });
});
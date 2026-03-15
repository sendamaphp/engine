<?php

use Sendama\Engine\Util\Path;

it('prefers a lowercase assets directory when it exists', function () {
  $workspace = sys_get_temp_dir() . '/sendama-path-lower-' . uniqid('', true);
  mkdir($workspace . '/assets', 0777, true);

  expect(Path::resolveAssetsDirectory($workspace))->toBe($workspace . '/assets');
});

it('falls back to a legacy uppercase Assets directory when present', function () {
  $workspace = sys_get_temp_dir() . '/sendama-path-upper-' . uniqid('', true);
  mkdir($workspace . '/Assets', 0777, true);

  expect(Path::resolveAssetsDirectory($workspace))->toBe($workspace . '/Assets');
});

it('defaults to the modern lowercase assets path when no directory exists yet', function () {
  $workspace = sys_get_temp_dir() . '/sendama-path-default-' . uniqid('', true);
  mkdir($workspace, 0777, true);

  expect(Path::resolveAssetsDirectory($workspace))->toBe($workspace . '/assets');
});

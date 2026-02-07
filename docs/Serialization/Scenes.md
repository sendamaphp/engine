# Sendama Scene Files (`*.scene.php`)

Sendama scenes are **PHP files that return an associative array** describing a scene’s metadata and its object hierarchy. The engine loads the file (for example `level01.scene.php`), then builds a `Scene` by creating an **anonymous class that extends ****AbstractScene**, and finally constructs the hierarchy based on the array returned by the file.

This document explains the expected structure of a Sendama scene file using a real-world example.

---

## File naming and conventions

Scene files follow this naming convention:

```
<scene-name>.scene.php
```

Example:

```
level01.scene.php
```

Because scene files are plain PHP, you may:

* Import classes with `use`
* Reference constants
* Use enums (for your own game logic, if desired)
* Perform calculations
* Build strings dynamically

The **only hard requirement** is that the file returns an array matching the scene schema.

---

## Top-level structure

A scene file must return an array with the following top-level keys:

```php
return [
  "width" => 80,
  "height" => 24,
  "environmentTileMapPath" => "Maps/level",
  "hierarchy" => [
    // GameObjects and UIElements
  ],
];
```

### `width`

Defines the logical width of the scene.

* Type: `int`
* Typically set using engine constants

Example:

```php
"width" => 80
```

### `height`

Defines the logical height of the scene.

* Type: `int`

Example:

```php
"height" => 24
```

### `environmentTileMapPath`

Specifies the base path to the environment or tilemap used by the scene.

* Type: `string`
* Interpreted by the game’s asset loading system

Example:

```php
"environmentTileMapPath" => "Maps/level"
```

### `hierarchy`

Defines the objects that exist in the scene at load time. Each entry represents an object to be instantiated.

* Type: `array<array<string, mixed>>`

---

## Scene hierarchy

Each entry in the `hierarchy` array describes **one object in the scene**. At load time, each entry must resolve to either:

* a `GameObject`, or
* a UI element that extends the `UIElement` abstract class (for example `Label`, `Text`, etc.)

The `type` field determines which class is instantiated, while the remaining fields configure the object.

### Common GameObject and UIElement fields

#### `type`

Fully-qualified class name of the object to create.

* Type: `class-string`

Example:

```php
"type" => GameObject::class
```

#### `name`

Human-readable identifier for debugging and tooling.

* Type: `string`

Example:

```php
"name" => "Player"
```

#### `tag`

Logical grouping identifier used for lookups, filtering, or gameplay logic. Tags are plain strings and are not enforced by the engine.

* Type: `string`
* Tags are user-defined and not provided by the Sendama Engine

Example:

```php
"tag" => "player"
```

#### `position`, `rotation`, `scale`

Defines transform-related properties for the object.

* Type: `array{x:int|float, y:int|float}`

Example:

```php
"position" => ["x" => 4, "y" => 12],
"rotation" => ["x" => 0, "y" => 0],
"scale" => ["x" => 1, "y" => 1],
```

Even if rotation or scale are not heavily used, they are part of the standard scene schema.

---

## Game objects and components

### `GameObject` definitions

A typical `GameObject` definition looks like this:

```php
[
  "type" => GameObject::class,
  "name" => "Player",
  "tag" => "player",
  "position" => ["x" => 4, "y" => 12],
  "rotation" => ["x" => 0, "y" => 0],
  "scale" => ["x" => 1, "y" => 1],
  "sprite" => [ /* optional */ ],
  "components" => [ /* behaviours */ ],
]
```

### `components`

Components attach behaviour to a game object. Each component entry specifies a class to instantiate and may optionally define property values that are applied during scene initialization.

* Type: `array<array{class: class-string, properties?: array<string, mixed>}>`

Basic example:

```php
"components" => [
  [ "class" => PlayerController::class ],
  [ "class" => Gun::class ],
]
```

#### `properties`

The `properties` key allows you to set default values for component properties at scene load time.

* Applies to:

    * public properties, and
    * protected or private properties marked with the `#[SerializeField]` attribute

Example:

```php
"components" => [
  [
    "class" => Gun::class,
    "properties" => [
      "fireRate" => 0.25,
      "ammo" => 30,
    ]
  ]
]
```

Property assignment occurs after the component is constructed and before the scene begins execution.

This design keeps scene files declarative while encapsulating behaviour inside components.

---

## Sprites and textures

### `sprite`

The `sprite` block describes how an object is rendered in the terminal.

```php
"sprite" => [
  "texture" => [
    "path" => "Textures/player",
    "position" => ["x" => 0, "y" => 0],
    "size" => ["x" => 1, "y" => 5],
  ]
]
```

#### `texture.path`

Path to the texture asset.

* Type: `string`

#### `texture.position`

Top-left coordinate of the texture region.

* Type: `array{x:int, y:int}`

#### `texture.size`

Width and height of the texture region.

* Type: `array{x:int, y:int}`

---

## UI elements (example: `Label`)

UI elements are declared directly in the scene hierarchy and must extend the `UIElement` abstract class. They may expose a different set of properties than `GameObject`.

Example:

```php
[
  "type" => Label::class,
  "name" => "Score",
  "tag" => "ui",
  "position" => ["x" => 4, "y" => 22],
  "size" => ["x" => 10, "y" => 1],
  "text" => "Score: " . str_pad('0', 3, '0', STR_PAD_LEFT),
]
```

### `size`

Defines the bounding area of the UI element.

* Type: `array{x:int, y:int}`

### `text`

Text content of the label.

* Type: `string`

Because this is PHP, text may be dynamically generated or formatted.

---

## Scene loading lifecycle

When a scene is loaded, the engine:

1. Includes the `*.scene.php` file
2. Reads the returned array
3. Creates an anonymous class extending `AbstractScene`
4. Applies scene-level metadata (`width`, `height`, etc.)
5. Iterates over `hierarchy` and instantiates each GameObject or UIElement
6. Attaches components and applies configuration

The scene file is therefore the **authoritative description** of the scene’s initial state.

---

## Authoring guidelines

* Keep scene files declarative; put logic in components
* Use tags consistently for querying and grouping
* Use PHP expressions sparingly but intentionally
* Treat scene files as data, not scripts

This format allows Sendama scenes to remain readable, expressive, and version-control friendly while still leveraging the full power of PHP.

<div align="center" style="margin: 32px">
    <img src="logo.png" alt="Sendama 2d Game Engine" width="128" height="128" align="center">    
</div>

# Sendama &mdash; A 2D Game Engine for Terminal Based Games
by amasiye313@gmail.com

## What is it?

![Sendama](examples/blasters/blasters.gif)

Sendama is a 2D game engine for creating terminal based games. It is designed to be simple and easy to use, while 
providing the necessary tools to create fun and engaging games. The engine is built using PHP and is designed to be 
cross-platform, so you can create games that run on Windows, Linux, and macOS. Rather than using complex graphics 
libraries, Sendama uses simple ASCII characters to create game worlds, making it easy to create games that are both 
fun and visually appealing. By leveraging the power of ANSI escape codes, Sendama allows you to create games that are 
interactive and engaging, with support for keyboard input, animations, and more.

### But why PHP?
Why not? PHP is a popular programming language that is widely used for web development, but it is also a powerful 
language in its own right. With the release of PHP 8, PHP has become even more powerful, with new features and 
improvements that make it a great choice for game development. By using PHP, you can take advantage of the language's 
simplicity and ease of use, while still being able to create games that are fun and engaging. And because PHP is 
cross-platform, you can create games that run on Windows, Linux, and macOS, without having to worry about 
compatibility issues. 

## Requirements
- PHP 8.3 or newer
- WSL (For Windows)
- Composer 2.7.1 or later 

## Installation

### Using the [Sendama CLI](https://github.com/atatusoft-ltd/sendama-console)
The recommended way to install Sendama is through the CLI. You can install Sendama by running the following commands:

```bash
composer global require sendamaphp/console
```

For more information and setup see [Sendama CLI](https://github.com/atatusoft-ltd/sendama-console).

#### Create a new game
Once the CLI is installed, you can start a new game by running the following command:

```bash
sendama new mygame
```

This will create a new game in the current directory. You can get started by changing into the game directory and running the game:

```bash
cd mygame
php mygame.php
``` 

### Using Composer
#### For Linux, BSD etc
```bash
mkdir /path/to/your/game
composer init
...
composer require sendamaphp/engine
```

#### For Windows
From the WSL terminal follow Linux instructions

#### OSX
```bash
mkdir /path/to/your/game
composer init
...
composer require sendamaphp/engine
```

## Usage
See [examples](examples/EXAMPLES.md) and [Documentation](docs/DOCS.md).

## Notes
The examples in [examples]() are made to demonstrate how Sendama may be used to make simple 2D games.

![GitHub Tag](https://img.shields.io/github/v/tag/Amund/wp-svgstore)
![Static Badge](https://img.shields.io/badge/wordpress-plugin-blue)

# wp-svgstore

**wp-svgstore** is a WordPress plugin that compiles a collection of SVG files into a single file and injects it into the footer of your website for efficient use of SVG icons.

## Features

- Combines multiple SVG files into a single sprite file.
- Automatically injects the sprite file into the footer of your website (front, admin and login pages).
- WP-CLI commands to create, check the existence of, and remove the sprite file.

## Requirements

- PHP 8.1 or later
- WordPress 6.1 or later
- WP-CLI, to build the sprite

## Installation

### With Composer

1. Add the repository to your `composer.json` file:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Amund/wp-svgstore.git"
        }
    ]
}
```

2. Install the plugin:

```sh
composer require amund/wp-svgstore
```

The package type is `wordpress-muplugin`: with [Bedrock](https://roots.io/bedrock/), it is installed in `web/app/mu-plugins/` and loaded automatically.

### Manually

Copy the plugin folder into `wp-content/plugins/` and activate it from the "Plugins" menu of the WordPress admin.

It can also be used as a must-use plugin, in `wp-content/mu-plugins/`. WordPress does not load plugins in subfolders of `mu-plugins`, so a loader file is needed, for example `wp-content/mu-plugins/wp-svgstore.php`:

```php
<?php
require_once __DIR__ . '/wp-svgstore/wp-svgstore.php';
```

## Workflow

1. Put the SVG files in the `svgstore/` folder of the theme.
2. Build the sprite: `wp svgstore create`. It writes `svgstore.svg` in the theme folder, and displays a warning for each file that could not be converted or may render incorrectly.
3. Use the icons in the theme with `svgstore::icon()`.

Run `wp svgstore create` again after adding, changing or removing an SVG file, and after updating the plugin, so that the sprite benefits from the changes of the new version.

## Usage

### `svgstore::icon($id, $attrs = [])`

Returns the markup of an icon: an `<svg>` element referencing the symbol of the sprite.

```php
// Decorative icon (hidden from screen readers)
echo svgstore::icon('arrow');
// <svg class="icon icon-arrow" role="img" aria-hidden="true"><use xlink:href="#arrow"></use></svg>

// Additional classes, as a string or an array
echo svgstore::icon('arrow', ['class' => 'is-big']);
echo svgstore::icon('arrow', ['class' => ['is-big', 'is-primary']]);

// Meaningful icon: the title adds a <title> element and an aria-label
echo svgstore::icon('download', ['title' => 'Download the brochure']);

// Any other attribute
echo svgstore::icon('arrow', ['width' => 24, 'height' => 24, 'focusable' => 'false']);
```

Attribute values, the id and the title are escaped (`esc_attr()` / `esc_html()`), so they can safely come from editable content. Attribute names are not escaped.

### Icon ids

The icon id is the file path relative to the `svgstore/` folder, without the `.svg` extension (case-insensitive), with folder separators replaced by dashes:

| File                     | Id         | Usage                          |
| ------------------------ | ---------- | ------------------------------ |
| `svgstore/arrow.svg`     | `arrow`    | `svgstore::icon('arrow')`      |
| `svgstore/social/x.svg`  | `social-x` | `svgstore::icon('social-x')`   |

If several files resolve to the same id (e.g. `social-x.svg` and `social/x.svg`), `wp svgstore create` fails and the existing sprite file is left untouched.

### WP-CLI

- `wp svgstore create`: Creates or updates the SVG sprite file. If no SVG file can be converted, or if several files resolve to the same id, it fails and leaves the existing sprite file untouched.
- `wp svgstore exists`: Checks if the SVG sprite file exists.
- `wp svgstore remove`: Removes the SVG sprite file.

## Configuration

### Paths

By default, SVG files are read from the `svgstore/` folder of the theme, and the sprite is written to `svgstore.svg` in the theme folder. Both paths can be changed with filters, for example in the `functions.php` of the theme:

```php
add_filter('svgstore_source', fn() => get_stylesheet_directory() . '/assets/icons');
add_filter('svgstore_destination', fn() => get_stylesheet_directory() . '/dist/svgstore.svg');
```

The filters apply both to the injection in the page and to the WP-CLI commands.

### Internal ids

SVG files often contain ids (gradients, clip paths, masks...), minified to `a`, `b`, `c`... by optimizers, and therefore identical from one file to another. Once merged in a single sprite, `url(#a)` would always target the first `#a` of the page. To avoid this, ids inside each icon are prefixed with the icon id when the sprite is created: `a` becomes `logo__a` in the `logo` icon, and references to it (`url(#a)`, `href="#a"`, `aria-labelledby`) are updated.

Icon ids themselves are not changed. To disable the prefixing:

```php
add_filter('svgstore_prefix_ids', '__return_false');
```

### Missing viewBox

When an SVG file has no `viewBox` attribute, it is rebuilt from its `width` and `height` attributes if both are plain numbers (`32` or `32px`), with a warning. Otherwise the icon will not scale, and a warning is displayed.

## Known limitations

- **Block editor iframe**: the sprite is injected in the admin page, but the content of the block editor may be displayed in an iframe (`iframe[name="editor-canvas"]`), which cannot see it. Icons in block previews then appear empty. A workaround is to clone the `.svgstore` element of the admin page into the body of the iframe, from a script enqueued with the `enqueue_block_editor_assets` hook.
- **Ids of the page**: an element of the page with the same id as an icon (e.g. `<div id="search">` and `search.svg`) appears before the sprite in the document, and takes precedence over the icon.
- **Attributes of the root `<svg>` element**: only `viewBox` is kept in the symbol. Other attributes, such as `fill="none"`, are not copied, so that the theme CSS (e.g. `.icon { fill: currentColor; }`) keeps control over the icons. Put such attributes on the inner elements of the SVG file instead.

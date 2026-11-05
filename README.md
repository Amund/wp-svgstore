![GitHub Tag](https://img.shields.io/github/v/tag/Amund/wp-svgstore)
![Static Badge](https://img.shields.io/badge/wordpress-plugin-blue)

# wp-svgstore

**wp-svgstore** is a WordPress plugin that compiles a collection of SVG files into a single file and injects it into the footer of your website for efficient use of SVG icons.

## Features

- Combines multiple SVG files into a single sprite file.
- Automatically injects the sprite file into the footer of your website.
- WP-CLI commands to create, check the existence of, and remove the sprite file.

## Installation

1. Add repository to your `composer.json` file:
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

2. Run the following command to install the plugin:
```sh
composer require amund/wp-svgstore
```

1. Download the plugin and place it in the `wp-content/mu-plugins` directory of your WordPress installation.
2. Activate the plugin through the "Plugins" menu in the WordPress admin dashboard.

## Usage

### Via WordPress Interface

The plugin automatically injects the SVG sprite file into the footer of your website. You can use the SVG icons by using the `svgstore::icon($id)` function, where `$id` is the identifier of the icon.

### Icon ids

SVG files are read from the `svgstore/` folder of the theme. The icon id is the file path relative to this folder, without the `.svg` extension, with folder separators replaced by dashes:

| File                     | Id         | Usage                          |
| ------------------------ | ---------- | ------------------------------ |
| `svgstore/arrow.svg`     | `arrow`    | `svgstore::icon('arrow')`      |
| `svgstore/social/x.svg`  | `social-x` | `svgstore::icon('social-x')`   |

If several files resolve to the same id (e.g. `social-x.svg` and `social/x.svg`), `wp svgstore create` fails and the existing sprite file is left untouched.

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

### Via WP-CLI

The plugin supports the following WP-CLI commands:

- `wp svgstore create`: Creates or updates the SVG sprite file in the template directory.
- `wp svgstore exists`: Checks if the SVG sprite file exists in the template directory.
- `wp svgstore remove`: Removes the SVG sprite file from the template directory.
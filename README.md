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

### Via WP-CLI

The plugin supports the following WP-CLI commands:

- `wp svgstore create`: Creates or updates the SVG sprite file in the template directory.
- `wp svgstore exists`: Checks if the SVG sprite file exists in the template directory.
- `wp svgstore remove`: Removes the SVG sprite file from the template directory.
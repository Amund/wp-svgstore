<?php

class svgstore
{

    /**
     * Generates an SVG element with a specified ID.
     *
     * This function returns an SVG element as a string with a given ID
     * and assigns a class to the SVG for styling purposes.
     *
     * @param string $id The identifier for the SVG element.
     * @return string The formatted SVG element as a string.
     */
    public static function icon($id): string
    {
        $class = ['icon', 'icon-' . $id];
        return strtr('<svg class="{class}" aria-hidden="true"><use xlink:href="#{id}"></use></svg>', [
            '{id}' => $id,
            '{class}' => implode(' ', $class),
        ]);
    }

    /**
     * Injects the SVG store in the footer of the page.
     *
     * If the page is an administration page, it is injected in the admin_footer action.
     * Otherwise, it is injected in the wp_footer action.
     *
     * If the page is the login page, it is also injected in the login_footer action.
     *
     * @return void
     */
    public static function init(): void
    {
        if (is_admin()) {
            add_action('admin_footer', [static::class, 'inject'], 100, 0);
        } else if (is_login()) {
            add_action('login_footer', [static::class, 'inject'], 100, 0);
        } else {
            add_action('wp_footer', [static::class, 'inject'], 100, 0);
        }
    }

    /**
     * Retrieves the path to the SVG store source directory.
     *
     * The source directory contains all the SVG files to be injected in the page footer.
     *
     * @return string The path to the SVG store source directory.
     */
    public static function source(): string
    {
        return get_template_directory() . '/svgstore';
    }

    /**
     * Retrieves the path to the SVG store destination file.
     *
     * The destination file is the file that is created by the create method and contains all the SVG symbols.
     *
     * @return string The path to the SVG store destination file.
     */
    public static function destination(): string
    {
        return get_template_directory() . '/svgstore.svg';
    }

    /**
     * Injects the SVG store in the page footer.
     *
     * Retrieves the content of the SVG store file and injects it in the page footer.
     * The SVG store is hidden by CSS and is only used as a reference for SVG icons.
     *
     * @return void
     */
    public static function inject(): void
    {
        if (self::exists()) {
            $content = @file_get_contents(self::destination()) ?: '';
        } else {
            $content = 'Svgstore file not found';
        }
        echo strtr('<div class="svgstore" aria-hidden="true" style="display:none;">{content}</div>', [
            '{content}' => $content,
        ]);
    }

    /**
     * Creates the SVG store file from the SVG files in the source directory.
     *
     * Creates a new SVG document containing all the SVG symbols found in the source directory.
     * The SVG symbols are wrapped in a <symbol> element with a unique id.
     * The viewBox attribute of the SVG symbol is copied from the original SVG element.
     * The content of the SVG element is copied recursively in the SVG symbol.
     * The resulting SVG document is written in the destination file.
     *
     * Returns an array containing the following information:
     *  - success: true if the file was written successfully, false otherwise.
     *  - source: the path to the source directory.
     *  - destination: the path to the destination file.
     *  - nb_svg: the number of SVG files found in the source directory.
     *  - nb_symbols: the number of SVG symbols written in the destination file.
     *
     * @return array The result of the file creation.
     */
    public static function create(): array
    {
        $source = self::source();
        $destination = self::destination();
        $symbols = [];
        $list = self::filemap($source);
        foreach ($list as $item) {
            if (str_ends_with($item, '.svg')) {
                $id = pathinfo($item, PATHINFO_FILENAME);
                $itemDom = new DomDocument();
                $itemDom->load($source . '/' . $item);
                $tags = $itemDom->getElementsByTagName('svg');
                if ($tags->length > 0) {
                    $tag = $tags->item(0);
                    $viewbox = $tag->getAttribute('viewBox');
                    $symbol = $itemDom->createElement('symbol');
                    $symbol->setAttribute('id', $id);
                    $symbol->setAttribute('viewBox', $viewbox);
                    $children = $tag->childNodes;
                    foreach (iterator_to_array($children) as $child) {
                        if ($child instanceof DOMElement) {
                            $clone = $child->cloneNode(true);
                            $symbol->appendChild($clone);
                        }
                    }
                    $symbols[] = simplexml_import_dom($symbol)->asXML();
                }
            }
        }

        $svg = strtr(
            '<svg xmlns="{xmlns}" xmlns:xlink="{xlink}">{symbols}</svg>',
            [
                '{xmlns}' => 'http://www.w3.org/2000/svg',
                '{xlink}' => 'http://www.w3.org/1999/xlink',
                '{symbols}' => implode('', $symbols),
            ]
        );
        $written = file_put_contents($destination, $svg, LOCK_EX);

        return [
            'success' => $written !== false,
            'source' => $source,
            'destination' => $destination,
            'nb_svg' => count($list),
            'nb_symbols' => count($symbols),
        ];
    }

    /**
     * Checks if the svgstore file exists.
     *
     * @return bool Whether the svgstore file exists.
     */
    public static function exists(): bool
    {
        return file_exists(self::destination());
    }

    /**
     * Removes the svgstore file.
     *
     * @return bool|null Whether the file has been removed, or NULL if the file does not exist.
     */
    public static function remove(): bool|null
    {
        if (self::exists() === false) {
            return NULL;
        } else {
            return @unlink(self::destination());
        }
    }

    /**
     * Recursive folder content mapping.
     *
     * @param string $folder The path to the folder.
     * @param int    $sort   The sorting flags (default: SORT_NATURAL | SORT_FLAG_CASE).
     *
     * @return array|false A list of relative paths to files and folders (or false if empty).
     */
    static function filemap($folder, $sort = SORT_NATURAL | SORT_FLAG_CASE)
    {
        if (is_dir($folder) && ($fp = @opendir($folder))) {
            $folders = [];
            $files = [];
            while (($entry = readdir($fp)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (is_dir($folder . '/' . $entry)) {
                    $folders[] = $entry;
                } elseif (is_file($folder . '/' . $entry)) {
                    $files[] = $entry;
                }
            }
            closedir($fp);
            if (empty($folders) && empty($files)) {
                return false;
            }
            sort($folders, $sort);
            sort($files, $sort);
            foreach ($folders as $key => $value) {
                $map = self::filemap($folder . '/' . $value);
                unset($folders[$key]);
                if (is_array($map) && !empty($map)) {
                    foreach ($map as $p) {
                        $folders[] = $value . '/' . $p;
                    }
                }
            }
            $output = [...$folders, ...$files];
            return $output;
        }

        return false;
    }
}

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
     * @param array $attrs Svg tag attributes. 'class' accepts a string or an array of classes,
     *                     appended to 'icon icon-{id}'. 'title' adds a <title> and an aria-label.
     *                     Values and title are escaped (esc_attr / esc_html), attribute names are not.
     * @return string The formatted SVG element as a string.
     */
    public static function icon($id, $attrs = []): string
    {
        $attrs['class'] = implode(' ', ['icon', 'icon-' . $id, ...(array) ($attrs['class'] ?? [])]);
        $attrs['role'] = $attrs['role'] ?? 'img';
        $attrs['aria-hidden'] = $attrs['aria-hidden'] ?? 'true';
        if (isset($attrs['title'])) {
            $title = '<title>' . esc_html($attrs['title']) . '</title>';
            $attrs['aria-label'] = $attrs['title'];
            unset($attrs['title']);
            unset($attrs['aria-hidden']);
        }
        foreach ($attrs as $k => $v) {
            $attrs[$k] = $k . '="' . esc_attr($v) . '"';
        }
        $attrs = implode(' ', $attrs);
        return strtr('<svg {attrs}>{title}<use xlink:href="#{id}"></use></svg>', [
            '{id}' => esc_attr($id),
            '{attrs}' => $attrs,
            '{title}' => $title ?? '',
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
     * Defaults to the "svgstore" folder of the theme, can be changed with the "svgstore_source" filter.
     *
     * @return string The path to the SVG store source directory.
     */
    public static function source(): string
    {
        return apply_filters('svgstore_source', get_template_directory() . '/svgstore');
    }

    /**
     * Retrieves the path to the SVG store destination file.
     *
     * The destination file is the file that is created by the create method and contains all the SVG symbols.
     * Defaults to "svgstore.svg" in the theme folder, can be changed with the "svgstore_destination" filter.
     *
     * @return string The path to the SVG store destination file.
     */
    public static function destination(): string
    {
        return apply_filters('svgstore_destination', get_template_directory() . '/svgstore.svg');
    }

    /**
     * Injects the SVG store in the page footer.
     *
     * Retrieves the content of the SVG store file and injects it in the page footer.
     * The SVG store is visually hidden (zero-sized, out of the flow) and is only used as a reference for SVG icons.
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
        // Not "display:none": gradients, patterns, clipPaths and masks defined in a display:none container
        // are not rendered by Chromium-based browsers.
        echo strtr('<div class="svgstore" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden;">{content}</div>', [
            '{content}' => $content,
        ]);
    }

    /**
     * Creates the SVG store file from the SVG files in the source directory.
     *
     * Creates a new SVG document containing all the SVG symbols found in the source directory.
     * The SVG symbols are wrapped in a <symbol> element with a unique id (see id()).
     * The viewBox attribute of the SVG symbol is copied from the original SVG element,
     * or rebuilt from its width and height when missing (see viewbox()).
     * The content of the SVG element is copied recursively in the SVG symbol.
     * Ids inside the SVG element are prefixed with the symbol id (see prefix_ids()),
     * unless the "svgstore_prefix_ids" filter returns false.
     * The resulting SVG document is written in the destination file.
     *
     * Returns an array containing the following information:
     *  - success: true if the file was written successfully, false otherwise.
     *  - source: the path to the source directory.
     *  - destination: the path to the destination file.
     *  - nb_svg: the number of SVG files (".svg" extension, case-insensitive) found in the source directory.
     *  - nb_symbols: the number of SVG symbols written in the destination file.
     *  - error: (only on failure: no symbol found, or duplicate ids) the reason; the destination file is left untouched.
     *  - duplicates: (only on duplicate ids) an array of id => list of files resolving to that id.
     *  - skipped: an array of file => reason, for SVG files that could not be converted to a symbol.
     *  - warnings: an array of file => message, for SVG files converted with a possible issue (e.g. missing viewBox).
     *
     * @return array The result of the file creation.
     */
    public static function create(): array
    {
        $source = self::source();
        $destination = self::destination();
        $symbols = [];
        $ids = [];
        $nb_svg = 0;
        $skipped = [];
        $warnings = [];
        $list = self::filemap($source) ?: [];
        $prefix_ids = apply_filters('svgstore_prefix_ids', true);
        // Collect XML errors instead of emitting raw PHP warnings.
        $libxml_previous = libxml_use_internal_errors(true);
        foreach ($list as $item) {
            if (str_ends_with(strtolower($item), '.svg')) {
                $nb_svg++;
                $id = self::id($item);
                $itemDom = new DomDocument();
                libxml_clear_errors();
                $itemDom->load($source . '/' . $item);
                $xml_errors = libxml_get_errors();
                libxml_clear_errors();
                $tags = $itemDom->getElementsByTagName('svg');
                if ($tags->length === 0) {
                    $skipped[$item] = !empty($xml_errors) ? trim($xml_errors[0]->message) : 'no <svg> element found';
                    continue;
                }
                if (!empty($xml_errors)) {
                    $warnings[$item] = trim($xml_errors[0]->message);
                }
                $tag = $tags->item(0);
                $viewbox = $tag->getAttribute('viewBox');
                if ($viewbox === '') {
                    $viewbox = self::viewbox($tag->getAttribute('width'), $tag->getAttribute('height'));
                    $warnings[$item] = $viewbox === ''
                        ? 'no viewBox attribute, the icon will not scale'
                        : 'no viewBox attribute, rebuilt from width and height: "' . $viewbox . '"';
                }
                if ($prefix_ids) {
                    self::prefix_ids($tag, $id);
                }
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
                $ids[$id][] = $item;
            }
        }
        libxml_use_internal_errors($libxml_previous);

        // Never overwrite an existing sprite with an empty one (missing or empty source directory).
        if (empty($symbols)) {
            return [
                'success' => false,
                'source' => $source,
                'destination' => $destination,
                'nb_svg' => $nb_svg,
                'nb_symbols' => 0,
                'skipped' => $skipped,
                'warnings' => $warnings,
                'error' => is_dir($source) ? 'No SVG symbol found in source directory' : 'Source directory not found',
            ];
        }

        // Several files resolving to the same id (e.g. "social-x.svg" and "social/x.svg"): refuse to write.
        $duplicates = array_filter($ids, fn($files) => count($files) > 1);
        if (!empty($duplicates)) {
            $details = [];
            foreach ($duplicates as $id => $files) {
                $details[] = '"' . $id . '" (' . implode(', ', $files) . ')';
            }
            return [
                'success' => false,
                'source' => $source,
                'destination' => $destination,
                'nb_svg' => $nb_svg,
                'nb_symbols' => 0,
                'skipped' => $skipped,
                'warnings' => $warnings,
                'error' => 'Duplicate ids: ' . implode(', ', $details),
                'duplicates' => $duplicates,
            ];
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
            'nb_svg' => $nb_svg,
            'nb_symbols' => count($symbols),
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * Prefixes the ids found inside an SVG element, and rewrites the references to them.
     *
     * Each file of the sprite has its own ids (gradients, clipPaths, masks...), often minified
     * to "a", "b", "c"... Once merged in a single sprite, "url(#a)" would target the first "#a"
     * of the page. Ids are prefixed with the symbol id: "a" => "logo__a" in the "logo" symbol.
     *
     * Rewritten references: url(#id) in attributes and <style> elements, href and xlink:href "#id",
     * aria-labelledby and aria-describedby.
     *
     * @param DOMElement $root The SVG element (its own id is left untouched).
     * @param string $prefix The prefix, usually the symbol id.
     * @return array The map of renamed ids (old => new), empty if there was no id.
     */
    public static function prefix_ids(DOMElement $root, string $prefix): array
    {
        $map = [];
        $xpath = new DOMXPath($root->ownerDocument);
        foreach ($xpath->query('.//*[@id]', $root) as $node) {
            $old = $node->getAttribute('id');
            $map[$old] = $prefix . '__' . $old;
            $node->setAttribute('id', $map[$old]);
        }
        if (empty($map)) {
            return $map;
        }

        $urls = fn(string $value): string => preg_replace_callback(
            '/url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)/',
            fn($m) => isset($map[$m[2]]) ? 'url(' . $m[1] . '#' . $map[$m[2]] . $m[1] . ')' : $m[0],
            $value
        );
        foreach ($xpath->query('.//*', $root) as $element) {
            foreach ($element->attributes as $attr) {
                $value = $attr->value;
                if ($attr->localName === 'href') {
                    if (str_starts_with($value, '#') && isset($map[substr($value, 1)])) {
                        $attr->value = '#' . $map[substr($value, 1)];
                    }
                } elseif ($attr->name === 'aria-labelledby' || $attr->name === 'aria-describedby') {
                    $tokens = preg_split('/\s+/', trim($value));
                    $attr->value = implode(' ', array_map(fn($t) => $map[$t] ?? $t, $tokens));
                } elseif (str_contains($value, 'url(')) {
                    $attr->value = $urls($value);
                }
            }
            if ($element->localName === 'style') {
                foreach ($element->childNodes as $text) {
                    if ($text instanceof DOMText) {
                        $text->data = $urls($text->data);
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Builds a viewBox from the width and height attributes of an SVG element.
     *
     * Only plain numbers, optionally in pixels, are supported: "32" or "32px" => "0 0 32 32".
     *
     * @param string $width The width attribute.
     * @param string $height The height attribute.
     * @return string The viewBox, or an empty string if it cannot be built.
     */
    public static function viewbox(string $width, string $height): string
    {
        $pattern = '/^\s*(\d+(?:\.\d+)?)\s*(?:px)?\s*$/';
        if (preg_match($pattern, $width, $w) && preg_match($pattern, $height, $h)) {
            return '0 0 ' . $w[1] . ' ' . $h[1];
        }
        return '';
    }

    /**
     * Computes the symbol id of an SVG file from its path relative to the source directory.
     *
     * Folder separators are replaced by dashes: "arrow.svg" => "arrow", "social/x.svg" => "social-x".
     *
     * @param string $path The path relative to the source directory.
     * @return string The symbol id.
     */
    public static function id(string $path): string
    {
        return str_replace('/', '-', substr($path, 0, -strlen('.svg')));
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

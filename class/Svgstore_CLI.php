<?php

/**
 * Manage svgstore.
 */

class Svgstore_CLI
{
    /**
     * Create or update svgstore file in template directory.
     */
    function create()
    {
        $response = svgstore::create();
        if ($response['success'] === false) {
            WP_CLI::error('File not created');
        } else {
            $message = "Svgstore created.\nnb_svg svg files found in source directory\nnb_symbols symbols written in destination file";
            $message = strtr($message, $response);
            WP_CLI::success($message);
        }
    }

    /**
     * Check if svgstore file exists in template directory.
     */
    function exists()
    {
        if (svgstore::exists()) {
            WP_CLI::success('Svgstore exists');
        } else {
            WP_CLI::error('Svgstore does not exist');
        }
    }

    /**
     * Remove svgstore.svg file in template directory.
     */
    function remove()
    {
        $return = svgstore::remove();
        if ($return === NULL) {
            WP_CLI::warning('Svgstore does not exist, nothing to remove.');
        } else if ($return === false) {
            WP_CLI::error('Cannot remove svgstore');
        } else {
            WP_CLI::success('Svgstore removed');
        }
    }
}

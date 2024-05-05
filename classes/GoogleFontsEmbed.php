<?php

/*
 * Google Fonts embed addon for Bear Framework
 * https://github.com/ivopetkov/google-fonts-embed-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed\Internal\Utilities;

/**
 *
 */
class GoogleFontsEmbed
{

    /**
     * Returns a URL for the font's CSS file.
     * 
     * @param string $name The name of The Google Font.
     * @param array $options Available values: display (auto, block, swap, fallback, optional)
     * @return string
     */
    public function getURL(string $name, array $options = []): string
    {
        $app = App::get();
        $context = $app->contexts->get(__DIR__);
        $display = isset($options['display']) ? $options['display'] : '';
        if (array_search($display, array_values(Utilities::$fontDisplayValues)) === false) {
            $display = '';
        }
        if ($display !== '') {
            $display = array_search($display, Utilities::$fontDisplayValues);
        }
        return $context->assets->getURL('assets/embed/css/' . str_replace(' ', '+', $name) . ($display !== '' ? '.d' . $display : '') . '.css', ['cacheMaxAge' => 86400 * 60, 'version' => '2']);
    }

    /**
     * Returns a list of URLs containing all files needed by the font
     *
     * @param string $name The name of The Google Font.
     * @param array $options Available values: display (auto, block, swap, fallback, optional)
     * @return array
     */
    public function getResourcesURLs(string $name, array $options = []): array
    {
        $fileDetails = Utilities::getCSSFileDetails($name);
        $urls = $fileDetails['fontFilesURLs'];
        array_unshift($urls, $this->getURL($name, $options));
        return $urls;
    }
}

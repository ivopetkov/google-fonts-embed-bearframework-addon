<?php

/*
 * Google Fonts embed addon for Bear Framework
 * https://github.com/ivopetkov/google-fonts-embed-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;

/**
 *
 */
class GoogleFontsEmbed
{

    /**
     * Returns a URL for the font's CSS file.
     * 
     * @param string $name The name of The Google Font.
     * @return string
     */
    public function getURL(string $name): string
    {
        $app = App::get();
        $context = $app->contexts->get(__DIR__);
        return $context->assets->getURL('assets/embed/css/' . str_replace(' ', '+', $name) . '.css', ['cacheMaxAge' => 86400 * 60]);
    }
}

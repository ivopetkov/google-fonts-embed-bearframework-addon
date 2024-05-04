<?php

/*
 * Google Fonts embed addon for Bear Framework
 * https://github.com/ivopetkov/google-fonts-embed-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed\Internal\Utilities;

$app = App::get();
$context = $app->contexts->get(__DIR__);

$context->classes
    ->add('IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed', 'classes/GoogleFontsEmbed.php')
    ->add('IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed\Internal\Utilities', 'classes/GoogleFontsEmbed/Internal/Utilities.php');

$app->shortcuts
    ->add('googleFontsEmbed', function () {
        return new \IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed();
    });

$context->assets
    ->addDir('assets/embed/css/')
    ->addDir('assets/embed/fonts/');

$app->assets
    ->addEventListener('beforePrepare', function (\BearFramework\App\Assets\BeforePrepareEventDetails $eventDetails) use ($app, $context) {
        $matchingDir = $context->dir . '/assets/embed/';
        if (strpos($eventDetails->filename, $matchingDir) === 0) {
            $filename = substr($eventDetails->filename, strlen($matchingDir));
            if (substr($filename, 0, 4) === 'css/') {
                $fontName = trim(str_replace('+', ' ', substr($filename, 4, -4))); // css/*.css
                if (strlen($fontName) === 0) {
                    return;
                }
                $eventDetails->filename = $app->data->getFilename(Utilities::getCSSFileDetails($fontName)['dataKey']);
            } elseif (substr($filename, 0, 6) === 'fonts/') {
                $eventDetails->filename = $app->data->getFilename(Utilities::getFontFileDetails($filename)['dataKey']);
            }
        }
    });

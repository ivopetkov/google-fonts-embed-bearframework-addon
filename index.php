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
    ->addEventListener('beforePrepare', function (\BearFramework\App\Assets\BeforePrepareEventDetails $eventDetails) use ($app, $context): void {
        $matchingDir = $context->dir . '/assets/embed/';
        if (strpos($eventDetails->filename, $matchingDir) === 0) {
            $filename = substr($eventDetails->filename, strlen($matchingDir));
            if (substr($filename, 0, 4) === 'css/') {
                $name = trim(str_replace('+', ' ', substr($filename, 4, -4))); // css/*.css
                $parts = explode('.', $name);
                $partsCount = count($parts);
                $options = [];
                if ($partsCount > 1) {
                    $name = $parts[0];
                    for ($i = 1; $i < $partsCount; $i++) {
                        $part = $parts[$i];
                        if (substr($part, 0, 1) === 'd') {
                            $display = substr($part, 1);
                            if (isset(Utilities::$fontDisplayValues[$display])) {
                                $options['display'] = Utilities::$fontDisplayValues[$display];
                            }
                        } elseif (substr($part, 0, 1) === 'f') {
                            $formats = str_split(substr($part, 1), 1);
                            $options['formats'] = [];
                            foreach ($formats as $format) {
                                if (isset(Utilities::$formatsValues[$format])) {
                                    $options['formats'][] = Utilities::$formatsValues[$format];
                                }
                            }
                        }
                    }
                }
                if (strlen($name) === 0) {
                    return;
                }
                $eventDetails->filename = $app->data->getFilename(Utilities::getCSSFileDetails($name, $options)['dataKey']);
            } elseif (substr($filename, 0, 6) === 'fonts/') {
                $eventDetails->filename = $app->data->getFilename(Utilities::getFontFileDetails($filename)['dataKey']);
            }
        }
    });

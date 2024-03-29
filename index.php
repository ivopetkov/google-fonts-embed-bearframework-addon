<?php

/*
 * Google Fonts embed addon for Bear Framework
 * https://github.com/ivopetkov/google-fonts-embed-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__DIR__);

$context->classes
    ->add('IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed', 'classes/GoogleFontsEmbed.php');

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
            $supportedPrefixes = [
                'a' => 'fonts.gstatic.com/s/'
            ];
            $filename = substr($eventDetails->filename, strlen($matchingDir));

            $download = function ($url, $userAgent = null) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                if ($userAgent !== null) {
                    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
                }
                $response = curl_exec($ch);
                $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return [$statusCode, $response];
            };

            if (substr($filename, 0, 4) === 'css/') {
                $fontName = trim(str_replace('+', ' ', substr($filename, 4, -4))); // css/*.css
                if (strlen($fontName) === 0) {
                    return;
                }
                $url = 'https://fonts.googleapis.com/css2?family=' . rawurlencode($fontName) . ':ital,wght@0,400;0,700;1,400;1,700&display=swap';
                $sourceDataKey = '.temp/google-fonts-embed/css/' . md5($url) . '.source';
                $sourceContent = $app->data->getValue($sourceDataKey);
                if ($sourceContent === null) {
                    $userAgents = [
                        'Mozilla/5.0', // truetype
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.3729.169 Safari/537.36', // woff
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36', // woff2
                    ];
                    $sourceContent = '';
                    foreach ($userAgents as $index => $userAgent) {
                        $urlResult = $download($url, $userAgent);
                        $statusCode = $urlResult[0];
                        if ($statusCode === 200) {
                            if (strpos($urlResult[1], 'license/googlerestricted') !== false) {
                                $sourceContent .= '// font css (' . $index . ') restricted';
                            } else {
                                $sourceContent .= trim($urlResult[1]);
                            }
                        } elseif ($statusCode === 404) {
                            $sourceContent .= '// font css (' . $index . ') not found';
                        } else {
                            $sourceContent .= '// font css (' . $index . ') not available (status code: ' . $statusCode . ')';
                        }
                        $sourceContent .= "\n";
                    }
                    $sourceContent = trim($sourceContent);
                    $app->data->set($app->data->make($sourceDataKey, $sourceContent));
                }
                $resultContent = $sourceContent;
                $matches = null;
                preg_match_all('/url\((.*?)\)/', $resultContent, $matches);
                if (isset($matches[1])) {
                    $matches[1] = array_unique($matches[1]);
                    foreach ($matches[1] as $fontURL) {
                        $fontURLParts = explode('//', $fontURL);
                        $newFontURL = 'about:blank';
                        if (isset($fontURLParts[1])) {
                            $fontURLPart1 = $fontURLParts[1];
                            foreach ($supportedPrefixes as $index => $prefix) {
                                if (strpos($fontURLPart1, $prefix) === 0) {
                                    $newFontURL = $context->assets->getURL('assets/embed/fonts/' . $index . '/' . substr($fontURLPart1, strlen($prefix)), ['cacheMaxAge' => 86400 * 60, 'version' => '1']);
                                    break;
                                }
                            }
                        }
                        $resultContent = str_replace($fontURL, $newFontURL, $resultContent);
                    }
                }
                $resultDataKey = '.temp/google-fonts-embed/css/' . md5($resultContent) . '.css';
                if (!$app->data->exists($resultDataKey)) {
                    $app->data->set($app->data->make($resultDataKey, $resultContent));
                }
                $eventDetails->filename = $app->data->getFilename($resultDataKey);
            } elseif (substr($filename, 0, 6) === 'fonts/') {
                $index = substr($filename, 6, 1);
                if (isset($supportedPrefixes[$index])) {
                    $fontURL = 'https://' . $supportedPrefixes[$index] . substr($filename, 8);
                    $extension = strtolower(pathinfo($fontURL, PATHINFO_EXTENSION));
                    if (preg_match('/^[a-z0-9]*$/', $extension) !== 1) {
                        $extension = 'unknown';
                    }
                } else {
                    $fontURL = 'invalid';
                    $extension = 'invalid';
                }
                $fontDataKey = '.temp/google-fonts-embed/fonts/' . md5($fontURL) . '.' . $extension;
                if (!$app->data->exists($fontDataKey)) {
                    if ($extension === 'unknown' || $extension === 'invalid') {
                        $fontContent = '// ' . $extension;
                    } else {
                        $urlResult = $download($fontURL);
                        $statusCode = $urlResult[0];
                        if ($statusCode === 200) {
                            $fontContent = $urlResult[1];
                        } elseif ($statusCode === 404) {
                            $fontContent = '// font file not found';
                        } else {
                            $fontContent = '// font file not available (status code: ' . $statusCode . ')';
                        }
                    }
                    $app->data->set($app->data->make($fontDataKey, $fontContent));
                }
                $eventDetails->filename = $app->data->getFilename($fontDataKey);
            }
        }
    });

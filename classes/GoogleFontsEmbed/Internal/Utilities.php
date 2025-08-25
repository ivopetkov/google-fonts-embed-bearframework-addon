<?php

/*
 * Google Fonts embed addon for Bear Framework
 * https://github.com/ivopetkov/google-fonts-embed-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\GoogleFontsEmbed\Internal;

use BearFramework\App;

/**
 *
 */
class Utilities
{

    static $supportedPrefixes = [
        'a' => 'fonts.gstatic.com/s/'
    ];

    static $fontDisplayValues = ['a' => 'auto', 'b' => 'block', 's' => 'swap', 'f' => 'fallback', 'o' => 'optional'];

    static $formatsValues = ['t' => 'ttf', 'o' => 'otf', 'w' => 'woff', 'f' => 'woff2'];

    static private $fontsDataCache = null;

    /**
     * 
     * @param string $name
     * @return array|null
     */
    static function getFontsData(string $name): ?array
    {
        if (self::$fontsDataCache === null) {
            $app = App::get();
            $context = $app->contexts->get(__DIR__);
            self::$fontsDataCache = require $context->dir . '/fonts.php';
        }
        return isset(self::$fontsDataCache[strtolower($name)]) ? self::$fontsDataCache[strtolower($name)] : null;
    }

    /**
     * 
     * @param string $url
     * @param string|null $userAgent
     * @return array
     */
    static private function getURLResponse(string $url, ?string $userAgent = null): array
    {
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
    }

    /**
     * 
     * @param string $name
     * @param array $options Available values: display (auto, block, swap, fallback, optional), Formats: ['woff2', 'woff', 'ttf']
     * @return array
     */
    static function getCSSFileDetails(string $name, array $options = []): array
    {
        $app = App::get();
        $context = $app->contexts->get(__DIR__);

        $result = [
            'dataKey' => '',
            'content' => '',
            'fontFilesURLs' => []
        ];
        $display = isset($options['display']) ? $options['display'] : 'auto';
        $formats = isset($options['formats']) ? $options['formats'] : [];
        if (array_search($display, array_values(self::$fontDisplayValues)) === false) {
            $display = 'auto';
        }
        $fontData = self::getFontsData($name);
        if (is_array($fontData)) {
            $weightsParam = [];
            foreach ($fontData['weights'] as $weight) {
                $weightsParam[] = strpos($weight, 'i') !== false ? '1,' . str_replace('i', '', $weight) : '0,' . $weight;
            }
            usort($weightsParam, function ($a, $b) {
                list($aItalic, $aWeight) = explode(',', $a);
                list($bItalic, $bWeight) = explode(',', $b);
                if ($aItalic !== $bItalic) {
                    return $aItalic === '0' ? -1 : 1;
                }
                return (int)$aWeight - (int)$bWeight;
            });
            $url = 'https://fonts.googleapis.com/css2?family=' . rawurlencode($fontData['name']) . ':ital,wght@' . implode(';', $weightsParam) . '&display=' . $display;
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
                    $urlResponse = self::getURLResponse($url, $userAgent);
                    $statusCode = $urlResponse[0];
                    if ($statusCode === 200) {
                        if (strpos($urlResponse[1], 'license/googlerestricted') !== false) {
                            $sourceContent .= '// font css (' . $index . ') restricted';
                        } else {
                            $sourceContent .= trim($urlResponse[1]);
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
            $resultContent = preg_replace('/\/\*.*\*\/\n/', '', $resultContent);
            $resultContent = preg_replace('/@font\-face {/', '@font-face{', $resultContent);
            $resultContent = preg_replace('/\n  /', '', $resultContent);
            $resultContent = preg_replace('/\n}/', '}', $resultContent);
            $resultContent = preg_replace('/: /', ':', $resultContent);
            $resultContent = preg_replace('/, U\+/', ',U+', $resultContent);

            if (!empty($formats)) {
                $matches = null;
                preg_match_all('/@font\-face{.*?}/', $resultContent, $matches);
                $temp = [];
                foreach ($matches[0] as $match) {
                    foreach ($formats as $format) {
                        if ($format === 'ttf' || $format === 'otf') {
                            $format = 'truetype';
                        }
                        if (strpos($match, 'format(\'' . $format . '\')') !== false) {
                            $temp[] = $match;
                            break;
                        }
                    }
                }
                $resultContent = implode("\n", $temp);
            }

            $matches = null;
            preg_match_all('/url\((.*?)\)/', $resultContent, $matches);
            if (isset($matches[1])) {
                $matches[1] = array_unique($matches[1]);
                foreach ($matches[1] as $fontURL) {
                    $fontURLParts = explode('//', $fontURL);
                    $newFontURL = 'about:blank';
                    if (isset($fontURLParts[1])) {
                        $fontURLPart1 = $fontURLParts[1];
                        foreach (self::$supportedPrefixes as $index => $prefix) {
                            if (strpos($fontURLPart1, $prefix) === 0) {
                                $newFontURL = $context->assets->getURL('assets/embed/fonts/' . $index . '/' . substr($fontURLPart1, strlen($prefix)), ['cacheMaxAge' => 86400 * 120, 'version' => '5']);
                                $result['fontFilesURLs'][] = $newFontURL;
                                break;
                            }
                        }
                    }
                    $resultContent = str_replace($fontURL, $newFontURL, $resultContent);
                }
            }
        } else {
            $resultContent = '// font not found';
        }
        $cssDataKey = '.temp/google-fonts-embed/css/' . md5($resultContent) . '.css';
        if (!$app->data->exists($cssDataKey)) {
            $app->data->set($app->data->make($cssDataKey, $resultContent));
        }
        $result['dataKey'] = $cssDataKey;
        $result['content'] = $resultContent;
        return $result;
    }

    /**
     * 
     * @param string $filename
     * @return array
     */
    static function getFontFileDetails(string $filename): array
    {
        $app = App::get();
        $index = substr($filename, 6, 1);
        if (isset(self::$supportedPrefixes[$index])) {
            $fontURL = 'https://' . self::$supportedPrefixes[$index] . substr($filename, 8);
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
                $urlResponse = self::getURLResponse($fontURL);
                $statusCode = $urlResponse[0];
                if ($statusCode === 200) {
                    $fontContent = $urlResponse[1];
                } elseif ($statusCode === 404) {
                    $fontContent = '// font file not found';
                } else {
                    $fontContent = '// font file not available (status code: ' . $statusCode . ')';
                }
            }
            $app->data->set($app->data->make($fontDataKey, $fontContent));
        }
        return [
            'dataKey' => $fontDataKey
        ];
    }
}

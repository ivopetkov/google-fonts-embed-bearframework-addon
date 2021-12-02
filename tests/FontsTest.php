<?php

/*
 * Google Fonts embed addon for Bear Framework
 * https://github.com/ivopetkov/google-fonts-embed-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class FontsTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testBasics()
    {
        $app = $this->getApp();

        $url = $app->googleFontsEmbed->getURL('Roboto');

        $response = $this->processRequest($this->makeRequest($url));
        $this->assertTrue($response instanceof \BearFramework\App\Response\FileReader);
        $cssFileContent = file_get_contents($response->filename);
        $this->assertTrue(strpos($cssFileContent, '@font-face') !== false);
    }
}

<?php

/**
 * Copyright 2020-2022 Markus Leupold-Löwenthal
 *
 * This file is part of wiki.md.
 *
 * wiki.md is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * wiki.md is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with wiki.md. If not, see <https://www.gnu.org/licenses/>.
 */

// Note: All tests operate on `dist/*` to QA the release version. You need to
//       build the project using `gulp dist` first.

namespace at\nerdreich\wiki;

require_once('dist/wiki.md/core/WikiCore.php');
require_once('dist/wiki.md/core/WikiUI.php');
require_once('dist/wiki.md/core/UserSession.php');

class WikiTestCase extends \PHPUnit\Framework\TestCase
{
    protected function getNewWiki(): WikiCore
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession('data', $config['login_simple']);
        return new WikiCore($config, $user);
    }

    protected function getNewWikiUI(string $wikiPath): WikiUI
    {
        $wiki = new \at\nerdreich\wiki\WikiUI($wikiPath);
        require_once $wiki->getThemeSetupFile();
        return $wiki;
    }

    protected function getAsPublicMethod(string $className, string $methodName): \ReflectionMethod
    {
        // make private method public for testing
        $reflector = new \ReflectionClass($className);
        $method = $reflector->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    public function getPrivateProperty(string $className, string $propertyName): \ReflectionProperty
    {
        $reflector = new \ReflectionClass($className);
        $property = $reflector->getProperty($propertyName);
        $property->setAccessible(true);
        return $property;
    }
}

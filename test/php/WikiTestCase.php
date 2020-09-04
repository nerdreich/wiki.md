<?php

/**
 * Copyright 2020 Markus Leupold-Löwenthal
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

namespace at\nerdreich;

require_once('dist/wiki.md/core/Wiki.php');
require_once('dist/wiki.md/core/WikiUI.php');
require_once('dist/wiki.md/core/UserSession.php');

class WikiTestCase extends \PHPUnit\Framework\TestCase
{
    protected function getNewWiki(): Wiki
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);
        return new Wiki($config, $user);
    }

    protected function getNewWikiUI(string $wikiPath): WikiUI
    {
        $ui = new \at\nerdreich\WikiUI($wikiPath);
        require_once $ui->getThemeSetupFile();
        return $ui;
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

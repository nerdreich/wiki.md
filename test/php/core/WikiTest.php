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

// Note: All tests operate on `dist/*` to QA the release version. You need to
//       build the project using `gulp dist` first.

namespace at\nerdreich;

require_once('dist/wiki.md/core/Wiki.php');

final class WikiTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultValues(): void
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $wiki = new Wiki($config);
        // $wiki->load('/'); // load the homepage

        $this->assertMatchesRegularExpression(
            '/^[0-9]+\.[0-9]+\.[0-9]+/',
            $wiki->getVersion()
        );
        $this->assertStringStartsWith('https://github', $wiki->getRepo());
    }

    public function testHomepage(): void
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $wiki = new Wiki($config);
        $wiki->load('/'); // load the homepage

        $this->assertTrue($wiki->exists());
        $this->assertEquals('/', $wiki->getPath());
        $this->assertEquals('Welcome!', $wiki->getTitle());
        $this->assertEquals($wiki->getTitle(), $wiki->getDescription());
        $this->assertEquals('wiki.md', $wiki->getAuthor());
        $this->assertMatchesRegularExpression(
            '/^[0-9][0-9]\.[0-9][0-9]\.[0-9][0-9][0-9][0-9] [0-9][0-9]:[0-9][0-9]/',
            $wiki->getDate()
        );
        $this->assertIsString($wiki->getContentHTML());
        $this->assertIsString($wiki->getMarkup());
    }
}

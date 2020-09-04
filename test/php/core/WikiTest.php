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

require_once('test/php/WikiTestCase.php');

final class WikiTest extends WikiTestCase
{
    public function testDefaultValues(): void
    {
        $wiki = $this->getNewWiki();

        $this->assertMatchesRegularExpression(
            '/^[0-9]+\.[0-9]+\.[0-9]+/',
            $wiki->getVersion()
        );
        $this->assertStringStartsWith('https://github', $wiki->getRepo());
    }

    public function testExists(): void
    {
        $wiki = $this->getNewWiki();
        $this->assertTrue($wiki->exists('/'));
        $this->assertTrue($wiki->exists('/README'));
        $this->assertFalse($wiki->exists('/README.md'));
        $this->assertFalse($wiki->exists('/docs'));
        $this->assertTrue($wiki->exists('/docs/'));
        $this->assertTrue($wiki->exists('/docs/README'));
        $this->assertFalse($wiki->exists('/docs/README.md'));
        $this->assertTrue($wiki->exists('/docs/install'));
        $this->assertFalse($wiki->exists('/docs/install.md'));
        $this->assertTrue($wiki->exists('/docs/./install'));
        $this->assertTrue($wiki->exists('/docs/../docs/more/../install'));
        $this->assertFalse($wiki->exists('/docs/../../README'));
    }

    public function testHomepage(): void
    {
        $wiki = $this->getNewWiki();

        // load the homepage
        $wiki->init('/');
        $wiki->readPage();

        $this->assertTrue($wiki->exists());
        $this->assertEquals('/', $wiki->getWikiPath());
        $this->assertEquals('Welcome!', $wiki->getTitle());
        $this->assertEquals($wiki->getTitle(), $wiki->getDescription());
        $this->assertEquals('wiki.md', $wiki->getAuthor());
        $this->assertIsObject($wiki->getDate());
        $this->assertIsString($wiki->getContentHTML());
        $this->assertIsString($wiki->getContentMarkup());
    }

    public function testwikiPathToContentFileFS(): void
    {
        $wiki = $this->getNewWiki();
        $method = $this->getAsPublicMethod('\at\nerdreich\Wiki', 'wikiPathToContentFileFS');

        $this->assertEquals(
            $wiki->getContentDirFS() . '/README.md',
            $method->invokeArgs($wiki, ['/'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/about.md',
            $method->invokeArgs($wiki, ['/about'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/README.md',
            $method->invokeArgs($wiki, ['/animal/'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/lion.md',
            $method->invokeArgs($wiki, ['/animal/lion'])
        );
    }

    public function testCanonicalWikiPath(): void
    {
        $wiki = $this->getNewWiki();
        $wiki->init('/animal/lion');
        $method = $this->getAsPublicMethod('\at\nerdreich\Wiki', 'canonicalWikiPath');

        // absolute pages
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/animal/lion']));
        $this->assertEquals('/animal/ape', $method->invokeArgs($wiki, ['/animal/ape']));
        $this->assertEquals('/plant/rose', $method->invokeArgs($wiki, ['/plant/rose']));
        $this->assertEquals('/plant/', $method->invokeArgs($wiki, ['/plant/']));
        $this->assertEquals('/plant', $method->invokeArgs($wiki, ['/plant']));
        $this->assertEquals('/', $method->invokeArgs($wiki, ['/']));

        // relative pages
        $this->assertEquals('/animal/ape', $method->invokeArgs($wiki, ['ape']));
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['lion']));
        $this->assertEquals('/animal/lion/claws', $method->invokeArgs($wiki, ['lion/claws']));
        $this->assertEquals('/plant', $method->invokeArgs($wiki, ['../plant']));
        $this->assertEquals('/plant/', $method->invokeArgs($wiki, ['../plant/']));
        $this->assertEquals('/plant/rose', $method->invokeArgs($wiki, ['../plant/rose']));
        $this->assertEquals('/animal', $method->invokeArgs($wiki, ['ape/../ape/../ape/..']));
        $this->assertEquals('/animal', $method->invokeArgs($wiki, ['ape/ape/../../ape/..']));
        $this->assertEquals('/plant', $method->invokeArgs($wiki, ['../animal/ape/../../plant']));
        $this->assertEquals('/plant/', $method->invokeArgs($wiki, ['../animal/ape/../../plant/']));
        $this->assertEquals(null, $method->invokeArgs($wiki, ['../../../../../../../../etc/passwd']));

        // folders
        $this->assertEquals('/animal/', $method->invokeArgs($wiki, ['/animal/']));
    }

    public function testFilterBrokenLinks(): void
    {
        $wiki = $this->getNewWiki();
        $method = $this->getAsPublicMethod('\at\nerdreich\Wiki', 'runFilters');

        // nothing changes while not logged-in
        $this->assertEquals('[link](/)', $method->invokeArgs($wiki, ['markup', '[link](/)', '/path']));
        $this->assertNotEquals(
            '[link](/nope){.broken}',
            $method->invokeArgs($wiki, ['markup', '[link](/nope)', '/path'])
        );

        // we can't test logged-in version without sessions/header errors :(
    }
}

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

namespace at\nerdreich\wiki;

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

    public function testAnonymoususer(): void
    {
        // anonymous users should only be able to read stuff
        $wiki = $this->getNewWiki();

        $this->assertFalse($wiki->mayReadPath(''));
        $this->assertFalse($wiki->mayCreatePath(''));
        $this->assertFalse($wiki->mayUpdatePath(''));
        $this->assertFalse($wiki->mayDeletePath(''));

        $this->assertTrue($wiki->mayReadPath('/'));
        $this->assertTrue($wiki->mayReadPath('/somepage'));
        $this->assertTrue($wiki->mayReadPath('/somefolder/'));
        $this->assertTrue($wiki->mayReadPath('/somefolder/somepage'));
        $this->assertTrue($wiki->mayReadPath('/somefolder/somefolder/somepage'));
        $this->assertFalse($wiki->mayCreatePath('/'));
        $this->assertFalse($wiki->mayCreatePath('/somepage'));
        $this->assertFalse($wiki->mayCreatePath('/somefolder/'));
        $this->assertFalse($wiki->mayCreatePath('/somefolder/somepage'));
        $this->assertFalse($wiki->mayCreatePath('/somefolder/somefolder/somepage'));
        $this->assertFalse($wiki->mayUpdatePath('/'));
        $this->assertFalse($wiki->mayUpdatePath('/somepage'));
        $this->assertFalse($wiki->mayUpdatePath('/somefolder/'));
        $this->assertFalse($wiki->mayUpdatePath('/somefolder/somepage'));
        $this->assertFalse($wiki->mayUpdatePath('/somefolder/somefolder/somepage'));
        $this->assertFalse($wiki->mayDeletePath('/'));
        $this->assertFalse($wiki->mayDeletePath('/somepage'));
        $this->assertFalse($wiki->mayDeletePath('/somefolder/'));
        $this->assertFalse($wiki->mayDeletePath('/somefolder/somepage'));
        $this->assertFalse($wiki->mayDeletePath('/somefolder/somefolder/somepage'));

        $this->assertTrue($wiki->mayReadPath('/docs'));
        $this->assertTrue($wiki->mayReadPath('/docs/'));
        $this->assertTrue($wiki->mayReadPath('/docs/install'));
        $this->assertTrue($wiki->mayReadPath('/docs/install/'));
        $this->assertFalse($wiki->mayCreatePath('/docs'));
        $this->assertFalse($wiki->mayCreatePath('/docs/'));
        $this->assertFalse($wiki->mayCreatePath('/docs/install'));
        $this->assertFalse($wiki->mayCreatePath('/docs/install/'));
        $this->assertFalse($wiki->mayUpdatePath('/docs'));
        $this->assertFalse($wiki->mayUpdatePath('/docs/'));
        $this->assertFalse($wiki->mayUpdatePath('/docs/install'));
        $this->assertFalse($wiki->mayUpdatePath('/docs/install/'));
        $this->assertFalse($wiki->mayDeletePath('/docs'));
        $this->assertFalse($wiki->mayDeletePath('/docs/'));
        $this->assertFalse($wiki->mayDeletePath('/docs/install'));
        $this->assertFalse($wiki->mayDeletePath('/docs/install/'));
    }

    public function testwikiPathToContentFileFS(): void
    {
        $wiki = $this->getNewWiki();
        $method = $this->getAsPublicMethod('\at\nerdreich\wiki\WikiCore', 'wikiPathToContentFileFS');

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
    public function testRealPath(): void
    {
        $wiki = $this->getNewWiki();
        $wiki->init('/animal/lion');
        $method = $this->getAsPublicMethod('\at\nerdreich\wiki\WikiCore', 'realPath');

        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/animal/lion']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/lion/']));
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/animal/./lion']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/./lion/']));
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/animal/././lion']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/././lion/']));
        $this->assertEquals('/lion', $method->invokeArgs($wiki, ['/animal/../lion']));
        $this->assertEquals('/lion/', $method->invokeArgs($wiki, ['/animal/../lion/']));
        $this->assertEquals('/animal/', $method->invokeArgs($wiki, ['/animal/lion/../']));
        $this->assertEquals('/animal/', $method->invokeArgs($wiki, ['/animal/lion/..']));

        $this->assertEquals('animal/lion', $method->invokeArgs($wiki, ['animal/lion']));
        $this->assertEquals('animal/lion/', $method->invokeArgs($wiki, ['animal/lion/']));
        $this->assertEquals('animal/lion', $method->invokeArgs($wiki, ['animal/./lion']));
        $this->assertEquals('animal/lion/', $method->invokeArgs($wiki, ['animal/./lion/']));
        $this->assertEquals('animal/lion', $method->invokeArgs($wiki, ['animal/././lion']));
        $this->assertEquals('animal/lion/', $method->invokeArgs($wiki, ['animal/././lion/']));
        $this->assertEquals('lion', $method->invokeArgs($wiki, ['animal/../lion']));
        $this->assertEquals('lion/', $method->invokeArgs($wiki, ['animal/../lion/']));
        $this->assertEquals('animal/', $method->invokeArgs($wiki, ['animal/lion/../']));
        $this->assertEquals('animal/', $method->invokeArgs($wiki, ['animal/lion/..']));

        // edge cases
        $this->assertEquals('', $method->invokeArgs($wiki, ['']));
        $this->assertEquals('', $method->invokeArgs($wiki, ['.']));
        $this->assertNull($method->invokeArgs($wiki, ['..']));
        $this->assertNull($method->invokeArgs($wiki, ['../']));
        $this->assertNull($method->invokeArgs($wiki, ['/..']));
        $this->assertNull($method->invokeArgs($wiki, ['/../']));
        $this->assertEquals('/', $method->invokeArgs($wiki, ['/']));
    }

    public function testCanonicalWikiPath(): void
    {
        $wiki = $this->getNewWiki();
        $wiki->init('/animal/lion');
        $method = $this->getAsPublicMethod('\at\nerdreich\wiki\WikiCore', 'canonicalWikiPath');

        // absolute pages
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/animal/lion']));
        $this->assertEquals('/animal/ape', $method->invokeArgs($wiki, ['/animal/ape']));
        $this->assertEquals('/plant/rose', $method->invokeArgs($wiki, ['/plant/rose']));
        $this->assertEquals('/plant/rose/', $method->invokeArgs($wiki, ['/plant/rose/']));
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
        $this->assertEquals('/animal/', $method->invokeArgs($wiki, ['ape/../ape/../ape/..']));
        $this->assertEquals('/animal/', $method->invokeArgs($wiki, ['ape/ape/../../ape/..']));
        $this->assertEquals('/plant', $method->invokeArgs($wiki, ['../animal/ape/../../plant']));
        $this->assertEquals('/plant/', $method->invokeArgs($wiki, ['../animal/ape/../../plant/']));
        $this->assertEquals(null, $method->invokeArgs($wiki, ['../../../../../../../../etc/passwd']));
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/./animal/lion']));
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/././animal/lion']));
        $this->assertEquals('/animal/lion', $method->invokeArgs($wiki, ['/animal/./lion']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/./animal/lion/']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/././animal/lion/']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/./lion/']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/lion/./']));
        $this->assertEquals('/animal/lion/', $method->invokeArgs($wiki, ['/animal/lion/.']));

        // folders
        $this->assertEquals('/animal/', $method->invokeArgs($wiki, ['/animal/']));
    }

    public function testFilterBrokenLinks(): void
    {
        $wiki = $this->getNewWiki();
        $method = $this->getAsPublicMethod('\at\nerdreich\wiki\WikiCore', 'runFilters');

        // nothing changes while not logged-in
        $this->assertEquals('[link](/)', $method->invokeArgs($wiki, ['markup', '[link](/)', '/path']));
        $this->assertNotEquals(
            '[link](/nope){.broken}',
            $method->invokeArgs($wiki, ['markup', '[link](/nope)', '/path'])
        );

        // we can't test logged-in version without sessions/header errors :(
    }
}

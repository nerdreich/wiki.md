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
require_once('dist/wiki.md/core/UserSession.php');

final class WikiTest extends \PHPUnit\Framework\TestCase
{
    // --- helper methods ------------------------------------------------------

    private function getNewWiki(): Wiki
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);
        return new Wiki($config, $user);
    }

    private function getAsPublicMethod(string $methodName): \ReflectionMethod
    {
        // make private method public for testing
        $reflector = new \ReflectionClass('\at\nerdreich\Wiki');
        $method = $reflector->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    // --- test methods --------------------------------------------------------

    public function testDefaultValues(): void
    {
        $wiki = $this->getNewWiki();

        $this->assertMatchesRegularExpression(
            '/^[0-9]+\.[0-9]+\.[0-9]+/',
            $wiki->getVersion()
        );
        $this->assertStringStartsWith('https://github', $wiki->getRepo());
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
        $method = $this->getAsPublicMethod('wikiPathToContentFileFS');

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
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/_media/lion.png',
            $method->invokeArgs($wiki, ['/animal/lion.png'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/_media/LION.JPG.PNG',
            $method->invokeArgs($wiki, ['/animal/LION.JPG.PNG'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/_media/lion.jpg',
            $method->invokeArgs($wiki, ['/animal/lion.jpg'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/_media/lion.JPEG',
            $method->invokeArgs($wiki, ['/animal/lion.JPEG'])
        );
        $this->assertEquals(
            $wiki->getContentDirFS() . '/animal/_media/lion.gif',
            $method->invokeArgs($wiki, ['/animal/lion.gif'])
        );
    }

    public function testCanonicalWikiPath(): void
    {
        $wiki = $this->getNewWiki();
        $wiki->init('/animal/lion');
        $method = $this->getAsPublicMethod('canonicalWikiPath');

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

    public function testSplitMacro(): void
    {
        // invalid empty macro
        list($command, $primary, $secondary) = Wiki::splitMacro('');
        $this->assertNull($command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // another invalid empty macro
        list($command, $primary, $secondary) = Wiki::splitMacro('{}');
        $this->assertNull($command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // another invalid empty macro
        list($command, $primary, $secondary) = Wiki::splitMacro('{{}}');
        $this->assertNull($command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // no-argument macro
        list($command, $primary, $secondary) = Wiki::splitMacro('{{name}}');
        $this->assertEquals('name', $command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // no-argument macro II
        list($command, $primary, $secondary) = Wiki::splitMacro("{{\n name\n\n}}");
        $this->assertEquals('name', $command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // no-argument macro III
        list($command, $primary, $secondary) = Wiki::splitMacro('{{n}}');
        $this->assertEquals('n', $command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // single-argument macro
        list($command, $primary, $secondary) = Wiki::splitMacro('{{name param}}');
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertNull($secondary);

        // single-argument macro II
        list($command, $primary, $secondary) = Wiki::splitMacro("{{ \n name \n\nparam\n  \n}}");
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertNull($secondary);

        // single-argument macro III
        list($command, $primary, $secondary) = Wiki::splitMacro('{{n p}}');
        $this->assertEquals('n', $command);
        $this->assertEquals('p', $primary);
        $this->assertNull($secondary);

        // full macro
        list($command, $primary, $secondary) = Wiki::splitMacro(
            '{{name param|key=value}}'
        );
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertEqualsCanonicalizing(['key' => 'value'], $secondary);

        // full macro II
        list($command, $primary, $secondary) = Wiki::splitMacro(
            '{{name param|key=value|some=other}}'
        );
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertEqualsCanonicalizing(
            ['key' => 'value', 'some' => 'other'],
            $secondary
        );

        // full macro III
        list($command, $primary, $secondary) = Wiki::splitMacro(
            "{{\n name\n  \n param \n \n\n| \n key = value\n |some\n=other\n }}"
        );
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertEqualsCanonicalizing(
            ['key' => 'value', 'some' => 'other'],
            $secondary
        );

        // full macro IV
        list($command, $primary, $secondary) = Wiki::splitMacro(
            '{{n p|k=v|s=o}}'
        );
        $this->assertEquals('n', $command);
        $this->assertEquals('p', $primary);
        $this->assertEqualsCanonicalizing(
            ['k' => 'v', 's' => 'o'],
            $secondary
        );
    }

    public function testMacroInclude(): void
    {
        $wiki = $this->getNewWiki();
        $method = $this->getAsPublicMethod('runFilters');

        $contentDirFS = $wiki->getContentDirFS() . '';

        // no macro
        $this->assertEquals('body', $method->invokeArgs($wiki, ['markup', 'body', '/path']));

        // invalid macro
        $this->assertEquals('{{include README}', $method->invokeArgs($wiki, ['raw', '{{include README}', '/path']));
        $this->assertEquals('{include README}', $method->invokeArgs($wiki, ['raw', '{include README}', '/path']));
        $this->assertEquals('include README', $method->invokeArgs($wiki, ['raw', 'include README', '/path']));

        // missing filename
        $this->assertEquals('{{error include-invalid}}', $method->invokeArgs($wiki, ['raw', '{{include}}', $contentDirFS . '/']));
        $this->assertEquals('{{error include-invalid}}', $method->invokeArgs($wiki, ['raw', '{{ include }}', $contentDirFS . '/']));

        // include same-level
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, ['raw', '{{include README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, ['raw', '{{include /README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, ['raw', '{{include meow}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, ['raw', '{{include /meow}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/A wiki.md primer/',
            $method->invokeArgs($wiki, ['raw', '{{include primer}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(// /docs/macros/install -> /docs/macros/install
            '/A wiki.md primer/',
            $method->invokeArgs($wiki, ['raw', '{{include primer}}', $contentDirFS . '/docs/macros'])
        );
        $this->assertMatchesRegularExpression(
            '/A wiki.md primer/',
            $method->invokeArgs($wiki, ['raw', '{{include primer}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/A wiki.md primer/',
            $method->invokeArgs($wiki, ['raw', '{{include primer}}', $contentDirFS . '/docs/README.md'])
        );

        // include higher-level
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, ['raw', '{{include ../README}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, ['raw', '{{include /README}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, ['raw', '{{include ../moo}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, ['raw', '{{include /moo}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, ['raw', '{{include ../../README}}', $contentDirFS . '/docs/README.md'])
        );

        // include deeper-level
        $this->assertMatchesRegularExpression(
            '/wiki.md Documentation/',
            $method->invokeArgs($wiki, ['raw', '{{include docs/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/wiki.md Documentation/',
            $method->invokeArgs($wiki, ['raw', '{{include /docs/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, ['raw', '{{include docs/moo}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, ['raw', '{{include /docs/moo}}', $contentDirFS . '/README.md'])
        );

        // include protected file
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, ['raw', '{{include docs/protected/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, ['raw', '{{include /docs/protected/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, ['raw', '{{include protected/README}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, ['raw', '{{include README}}', $contentDirFS . '/docs/protected/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, ['raw', '{{include ../README}}', $contentDirFS . '/docs/protected/too/README.md'])
        );

        // ignore secondary params
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, ['raw', '{{include README | a=b | c=d }}', $contentDirFS . '/README.md'])
        );
    }
}

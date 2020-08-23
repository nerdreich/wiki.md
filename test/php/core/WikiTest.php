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
        $this->assertMatchesRegularExpression(
            '/^[0-9][0-9]\.[0-9][0-9]\.[0-9][0-9][0-9][0-9] [0-9][0-9]:[0-9][0-9]/',
            $wiki->getDate()
        );
        $this->assertIsString($wiki->getContentHTML());
        $this->assertIsString($wiki->getContentMarkup());
    }

    public function testCanonicalWikiPath(): void
    {
        $wiki = $this->getNewWiki();
        $wiki->init('/animal/lion');

        // absolute
        $this->assertEquals('/animal/lion', $wiki->canonicalWikiPath('/animal/lion'));
        $this->assertEquals('/animal/ape', $wiki->canonicalWikiPath('/animal/ape'));
        $this->assertEquals('/plant/rose', $wiki->canonicalWikiPath('/plant/rose'));
        $this->assertEquals('/plant/', $wiki->canonicalWikiPath('/plant/'));
        $this->assertEquals('/plant', $wiki->canonicalWikiPath('/plant'));
        $this->assertEquals('/', $wiki->canonicalWikiPath('/'));

        // relative
        $this->assertEquals('/animal/ape', $wiki->canonicalWikiPath('ape'));
        $this->assertEquals('/animal/lion', $wiki->canonicalWikiPath('lion'));
        $this->assertEquals('/animal/lion/claws', $wiki->canonicalWikiPath('lion/claws'));
        $this->assertEquals('/plant', $wiki->canonicalWikiPath('../plant'));
        $this->assertEquals('/plant/', $wiki->canonicalWikiPath('../plant/'));
        $this->assertEquals('/plant/rose', $wiki->canonicalWikiPath('../plant/rose'));
        $this->assertEquals('/animal', $wiki->canonicalWikiPath('ape/../ape/../ape/..'));
        $this->assertEquals('/animal', $wiki->canonicalWikiPath('ape/ape/../../ape/..'));
        $this->assertEquals('/plant', $wiki->canonicalWikiPath('../animal/ape/../../plant'));
        $this->assertEquals('/plant/', $wiki->canonicalWikiPath('../animal/ape/../../plant/'));
        $this->assertEquals('/', $wiki->canonicalWikiPath('../../../../../../../../etc/passwd'));
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
        $method = $this->getAsPublicMethod('resolveMacros');

        // no macro
        $this->assertEquals('body', $method->invokeArgs($wiki, array('body', '/path')));

        // invalid macro
        $this->assertEquals('{{include README}', $method->invokeArgs($wiki, array('{{include README}', '/path')));
        $this->assertEquals('{include README}', $method->invokeArgs($wiki, array('{include README}', '/path')));
        $this->assertEquals('include README', $method->invokeArgs($wiki, array('include README', '/path')));

        // missing filename
        $this->assertEquals('{{error include-not-found}}', $method->invokeArgs($wiki, array('{{include}}', '/')));
        $this->assertEquals('{{error include-not-found}}', $method->invokeArgs($wiki, array('{{ include }}', '/')));

        // include same-level
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, array('{{include README}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, array('{{include /README}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include meow}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include /meow}}', '/'))
        );

        // include higher-level
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, array('{{include ../README}}', '/docs/'))
        );
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, array('{{include /README}}', '/docs/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include ../meow}}', '/docs/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include /meow}}', '/docs/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include ../../README}}', '/docs/'))
        );

        // include deeper-level
        $this->assertMatchesRegularExpression(
            '/wiki.md Documentation/',
            $method->invokeArgs($wiki, array('{{include docs/README}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/wiki.md Documentation/',
            $method->invokeArgs($wiki, array('{{include /docs/README}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include docs/meow}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($wiki, array('{{include /docs/meow}}', '/'))
        );

        // include protected file
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, array('{{include docs/protected/README}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, array('{{include /docs/protected/README}}', '/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, array('{{include protected/README}}', '/docs/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, array('{{include README}}', '/docs/protected/'))
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($wiki, array('{{include ../README}}', '/docs/protected/too/'))
        );

        // ignore secondary params
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($wiki, array('{{include README | a=b | c=d }}', '/'))
        );
    }
}

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

final class MacroTest extends WikiTestCase
{
    public function testSplitMacro(): void
    {
        // invalid empty macro
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('');
        $this->assertNull($command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // another invalid empty macro
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('{}');
        $this->assertNull($command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // another invalid empty macro
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('{{}}');
        $this->assertNull($command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // no-argument macro
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('{{name}}');
        $this->assertEquals('name', $command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // no-argument macro II
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro("{{\n name\n\n}}");
        $this->assertEquals('name', $command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // no-argument macro III
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('{{n}}');
        $this->assertEquals('n', $command);
        $this->assertNull($primary);
        $this->assertNull($secondary);

        // single-argument macro
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('{{name param}}');
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertNull($secondary);

        // single-argument macro II
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro("{{ \n name \n\nparam\n  \n}}");
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertNull($secondary);

        // single-argument macro III
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro('{{n p}}');
        $this->assertEquals('n', $command);
        $this->assertEquals('p', $primary);
        $this->assertNull($secondary);

        // full macro
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro(
            '{{name param|key=value}}'
        );
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertEqualsCanonicalizing(['key' => 'value'], $secondary);

        // full macro II
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro(
            '{{name param|key=value|some=other}}'
        );
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertEqualsCanonicalizing(
            ['key' => 'value', 'some' => 'other'],
            $secondary
        );

        // full macro III
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro(
            "{{\n name\n  \n param \n \n\n| \n key = value\n |some\n=other\n }}"
        );
        $this->assertEquals('name', $command);
        $this->assertEquals('param', $primary);
        $this->assertEqualsCanonicalizing(
            ['key' => 'value', 'some' => 'other'],
            $secondary
        );

        // full macro IV
        list($command, $primary, $secondary) = \at\nerdreich\wiki\MacroPlugin::splitMacro(
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
        $core = $this->getNewWikiUI('/test')->core;
        $method = $this->getAsPublicMethod('\at\nerdreich\wiki\WikiCore', 'runFilters');

        $contentDirFS = $core->getContentDirFS() . '';

        // no macro
        $this->assertEquals('body', $method->invokeArgs($core, ['markup', 'body', $contentDirFS . '/README.md']));

        // invalid macro
        $this->assertEquals('{{include README}', $method->invokeArgs($core, ['raw', '{{include README}', $contentDirFS . '/README.md']));
        $this->assertEquals('{include README}', $method->invokeArgs($core, ['raw', '{include README}', $contentDirFS . '/README.md']));
        $this->assertEquals('include README', $method->invokeArgs($core, ['raw', 'include README', $contentDirFS . '/README.md']));

        // missing filename
        $this->assertEquals(
            '{{error include-invalid}}',
            $method->invokeArgs($core, ['raw', '{{include}}', $contentDirFS . '/'])
        );
        $this->assertEquals(
            '{{error include-invalid}}',
            $method->invokeArgs($core, ['raw', '{{ include }}', $contentDirFS . '/'])
        );

        // include same-level
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($core, ['raw', '{{include README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($core, ['raw', '{{include /README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($core, ['raw', '{{include meow}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($core, ['raw', '{{include /meow}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/A wiki.md primer/',
            $method->invokeArgs($core, ['raw', '{{include primer}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(// /docs/macros/install -> /docs/macros/install
            '/A wiki.md primer/',
            $method->invokeArgs($core, ['raw', '{{include primer}}', $contentDirFS . '/docs/macros'])
        );
        $this->assertMatchesRegularExpression(
            '/A wiki.md primer/',
            $method->invokeArgs($core, ['raw', '{{include primer}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/A wiki.md primer/',
            $method->invokeArgs($core, ['raw', '{{include primer}}', $contentDirFS . '/docs/README.md'])
        );

        // include higher-level
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($core, ['raw', '{{include ../README}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($core, ['raw', '{{include /README}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($core, ['raw', '{{include ../moo}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($core, ['raw', '{{include /moo}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($core, ['raw', '{{include ../../README}}', $contentDirFS . '/docs/README.md'])
        );

        // include deeper-level
        $this->assertMatchesRegularExpression(
            '/wiki.md Documentation/',
            $method->invokeArgs($core, ['raw', '{{include docs/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/wiki.md Documentation/',
            $method->invokeArgs($core, ['raw', '{{include /docs/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($core, ['raw', '{{include docs/moo}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-not-found}}/',
            $method->invokeArgs($core, ['raw', '{{include /docs/moo}}', $contentDirFS . '/README.md'])
        );

        // include protected file
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($core, ['raw', '{{include docs/protected/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($core, ['raw', '{{include /docs/protected/README}}', $contentDirFS . '/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($core, ['raw', '{{include protected/README}}', $contentDirFS . '/docs/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($core, ['raw', '{{include README}}', $contentDirFS . '/docs/protected/README.md'])
        );
        $this->assertMatchesRegularExpression(
            '/{{error include-permission-denied}}/',
            $method->invokeArgs($core, ['raw', '{{include ../README}}', $contentDirFS . '/docs/protected/too/README.md'])
        );

        // ignore secondary params
        $this->assertMatchesRegularExpression(
            '/This is the homepage/',
            $method->invokeArgs($core, ['raw', '{{include README | a=b | c=d }}', $contentDirFS . '/README.md'])
        );
    }
}

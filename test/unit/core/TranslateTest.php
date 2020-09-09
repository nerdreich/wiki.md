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

require_once('dist/wiki.md/core/Translate.php');

final class TranslateTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadLanguages(): void
    {
        $this->assertTrue(
            Translate::loadLanguage('dist/wiki.md/themes/elegant/I18N/de.yaml')
        );
        $this->assertFalse(
            Translate::loadLanguage('dist/wiki.md/themes/elegant/I18N/xx.yaml')
        );
    }

    public function testTranslateDefault(): void
    {
        Translate::loadLanguage('dist/wiki.md/themes/elegant/I18N/xx.yaml'); // empty translations
        $this->assertEquals('Cancel', ___('Cancel'));
        Translate::loadLanguage('dist/wiki.md/themes/elegant/I18N/de.yaml');
        $this->assertEquals('Abbrechen', ___('Cancel'));
        $this->assertEquals('Not translated yet.', ___('Not translated yet.'));
    }
}

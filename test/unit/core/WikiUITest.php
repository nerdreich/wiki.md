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

require_once('test/unit/WikiTestCase.php');

final class WikiUITest extends WikiTestCase
{
    public function testDefaultValues(): void
    {
        $wikiUI = $this->getNewWikiUi('/test');

        $this->assertStringStartsWith('https://github', $wikiUI->getRepo());
    }

    public function testSanitizePath(): void
    {
        $wikiUI = $this->getNewWikiUi('/test');

        $this->assertEquals('/animal', $wikiUI->sanitizePath('/animal'));
        $this->assertEquals('/animal/Lion', $wikiUI->sanitizePath('///animal//Lion'));
        $this->assertEquals('/animal/li on/', $wikiUI->sanitizePath('/animal/li on/'));
        $this->assertEquals('/animal/łīöň/', $wikiUI->sanitizePath('/animal/łīöň/'));
        $this->assertEquals('/animal[lion]', $wikiUI->sanitizePath('/animal[lion]'));
        $this->assertEquals('/animal/lion.jpg', $wikiUI->sanitizePath('/animal/lion.jpg'));
        $this->assertEquals('/./lion.jpg', $wikiUI->sanitizePath('/../lion.*.jpg'));
        $this->assertEquals('/animal/././lion', $wikiUI->sanitizePath('/animal/.///./lion'));
    }
}

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

final class MediaTest extends WikiTestCase
{
    public function testwikiPathToContentFileFS(): void
    {
        $ui = $this->getNewWikiUI('/test');
        $plugin = $ui->wiki->getPlugin('media');
        $method = $this->getAsPublicMethod('\at\nerdreich\MediaPlugin', 'getMediaFileFS');

        $this->assertEquals(
            $ui->wiki->getContentDirFS() . '/animal/_media/lion.png',
            $method->invokeArgs($plugin, ['/animal/lion.png'])
        );
        $this->assertEquals(
            $ui->wiki->getContentDirFS() . '/animal/_media/LION.JPG.PNG',
            $method->invokeArgs($plugin, ['/animal/LION.JPG.PNG'])
        );
        $this->assertEquals(
            $ui->wiki->getContentDirFS() . '/animal/_media/lion.jpg',
            $method->invokeArgs($plugin, ['/animal/lion.jpg'])
        );
        $this->assertEquals(
            $ui->wiki->getContentDirFS() . '/animal/_media/lion.gif',
            $method->invokeArgs($plugin, ['/animal/lion.gif'])
        );
    }

    public function testAnonymoususer(): void
    {
        // anonymous users should only be able to read stuff
        $ui = $this->getNewWikiUI('/test');
        $plugin = $ui->wiki->getPlugin('media');

        $this->assertFalse($plugin->mayMedia(''));

        $this->assertFalse($plugin->mayMedia('/'));
        $this->assertFalse($plugin->mayMedia('/somepage'));
        $this->assertFalse($plugin->mayMedia('/somefolder/'));
        $this->assertFalse($plugin->mayMedia('/somefolder/somepage'));
        $this->assertFalse($plugin->mayMedia('/somefolder/somefolder/somepage'));

        $this->assertFalse($plugin->mayMedia('/docs'));
        $this->assertFalse($plugin->mayMedia('/docs/'));
        $this->assertFalse($plugin->mayMedia('/docs/install'));
        $this->assertFalse($plugin->mayMedia('/docs/install/'));
    }

    public function testDocsUser(): void
    {
        // the 'docs' user is allowed to do everything one subdir, but not in others
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'docs'); // pseudo-login

        $this->assertFalse($user->hasExplicitPermission('userMedia', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('userMedia', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('userMedia', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('userMedia', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('userMedia', '/docs/more/infos/'));

        $this->assertFalse($user->hasExplicitPermission('userMedia', '/'));
        $this->assertFalse($user->hasExplicitPermission('userMedia', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userMedia', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('userMedia', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userMedia', '/somefolder/somefolder/somepage'));
    }
}

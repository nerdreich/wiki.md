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

final class UserSessionTest extends WikiTestCase
{
    private static $htpasswd = '';

    // --- tests ---------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$htpasswd = dirname(__FILE__) . '/../../../dist/wiki.md/data/.htpasswd';

        // set logindata to known passwords (adm, doc)
        file_put_contents(
            self::$htpasswd,
            'admin:$2y$05$mcqOIM9K4lZfujCONaP7yu32/L5Ptzndf2xRN1/3EMO/UM7qicl8i' . PHP_EOL .
            'docs:$2y$05$KiA/6HVXZ6sQsY9c8.0j/.g6HjBHwrV8lmLvlvxo76EdeIbOzgyBq' . PHP_EOL
        );
    }

    public function testDocsUser(): void
    {
        // the 'docs' user is allowed to do everything one subdir, but not in others
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession('data', $config['login_simple']);
        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($user, 'docs'); // pseudo-login

        $this->assertFalse($user->hasPermission('pageCreate', '/docs')); // a page in the root folder!
        $this->assertTrue($user->hasPermission('pageCreate', '/docs/'));
        $this->assertTrue($user->hasPermission('pageCreate', '/docs/install'));
        $this->assertTrue($user->hasPermission('pageCreate', '/docs/more/infos'));
        $this->assertTrue($user->hasPermission('pageCreate', '/docs/more/infos/'));
        $this->assertTrue($user->hasPermission('pageRead', '/docs'));
        $this->assertTrue($user->hasPermission('pageRead', '/docs/'));
        $this->assertTrue($user->hasPermission('pageRead', '/docs/install'));
        $this->assertTrue($user->hasPermission('pageRead', '/docs/more/infos'));
        $this->assertTrue($user->hasPermission('pageRead', '/docs/more/infos/'));
        $this->assertFalse($user->hasPermission('pageUpdate', '/docs'));
        $this->assertTrue($user->hasPermission('pageUpdate', '/docs/'));
        $this->assertTrue($user->hasPermission('pageUpdate', '/docs/install'));
        $this->assertTrue($user->hasPermission('pageUpdate', '/docs/more/infos'));
        $this->assertTrue($user->hasPermission('pageUpdate', '/docs/more/infos/'));
        $this->assertFalse($user->hasPermission('pageDelete', '/docs'));
        $this->assertTrue($user->hasPermission('pageDelete', '/docs/'));
        $this->assertTrue($user->hasPermission('pageDelete', '/docs/install'));
        $this->assertTrue($user->hasPermission('pageDelete', '/docs/more/infos'));
        $this->assertTrue($user->hasPermission('pageDelete', '/docs/more/infos/'));
        $this->assertFalse($user->hasPermission('userAdmin', '/docs'));
        $this->assertFalse($user->hasPermission('userAdmin', '/docs/'));
        $this->assertFalse($user->hasPermission('userAdmin', '/docs/install'));
        $this->assertFalse($user->hasPermission('userAdmin', '/docs/more/infos'));
        $this->assertFalse($user->hasPermission('userAdmin', '/docs/more/infos/'));

        $this->assertFalse($user->hasPermission('pageCreate', '/'));
        $this->assertFalse($user->hasPermission('pageCreate', '/somepage'));
        $this->assertFalse($user->hasPermission('pageCreate', '/somefolder/'));
        $this->assertFalse($user->hasPermission('pageCreate', '/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('pageCreate', '/somefolder/somefolder/somepage'));
        $this->assertTrue($user->hasPermission('pageRead', '/'));
        $this->assertTrue($user->hasPermission('pageRead', '/somepage'));
        $this->assertTrue($user->hasPermission('pageRead', '/somefolder/'));
        $this->assertTrue($user->hasPermission('pageRead', '/somefolder/somepage'));
        $this->assertTrue($user->hasPermission('pageRead', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('pageUpdate', '/'));
        $this->assertFalse($user->hasPermission('pageUpdate', '/somepage'));
        $this->assertFalse($user->hasPermission('pageUpdate', '/somefolder/'));
        $this->assertFalse($user->hasPermission('pageUpdate', '/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('pageUpdate', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('pageDelete', '/'));
        $this->assertFalse($user->hasPermission('pageDelete', '/somepage'));
        $this->assertFalse($user->hasPermission('pageDelete', '/somefolder/'));
        $this->assertFalse($user->hasPermission('pageDelete', '/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('pageDelete', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('userAdmin', '/'));
        $this->assertFalse($user->hasPermission('userAdmin', '/somepage'));
        $this->assertFalse($user->hasPermission('userAdmin', '/somefolder/'));
        $this->assertFalse($user->hasPermission('userAdmin', '/somefolder/somepage'));
        $this->assertFalse($user->hasPermission('userAdmin', '/somefolder/somefolder/somepage'));
    }
}

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

require_once('dist/wiki.md/core/UserSession.php');

final class UserSessionTest extends \PHPUnit\Framework\TestCase
{
    public function testAnonymoususer(): void
    {
        // anonymous users should only be able to read stuff

        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config); // this will be the anonymous user

        $this->assertFalse($user->mayRead(''));
        $this->assertFalse($user->mayCreate(''));
        $this->assertFalse($user->mayUpdate(''));
        $this->assertFalse($user->mayDelete(''));

        $this->assertTrue($user->mayRead('/'));
        $this->assertTrue($user->mayRead('/somepage'));
        $this->assertTrue($user->mayRead('/somefolder/'));
        $this->assertTrue($user->mayRead('/somefolder/somepage'));
        $this->assertTrue($user->mayRead('/somefolder/somefolder/somepage'));
        $this->assertFalse($user->mayCreate('/'));
        $this->assertFalse($user->mayCreate('/somepage'));
        $this->assertFalse($user->mayCreate('/somefolder/'));
        $this->assertFalse($user->mayCreate('/somefolder/somepage'));
        $this->assertFalse($user->mayCreate('/somefolder/somefolder/somepage'));
        $this->assertFalse($user->mayUpdate('/'));
        $this->assertFalse($user->mayUpdate('/somepage'));
        $this->assertFalse($user->mayUpdate('/somefolder/'));
        $this->assertFalse($user->mayUpdate('/somefolder/somepage'));
        $this->assertFalse($user->mayUpdate('/somefolder/somefolder/somepage'));
        $this->assertFalse($user->mayDelete('/'));
        $this->assertFalse($user->mayDelete('/somepage'));
        $this->assertFalse($user->mayDelete('/somefolder/'));
        $this->assertFalse($user->mayDelete('/somefolder/somepage'));
        $this->assertFalse($user->mayDelete('/somefolder/somefolder/somepage'));

        $this->assertTrue($user->mayRead('/docs'));
        $this->assertTrue($user->mayRead('/docs/'));
        $this->assertTrue($user->mayRead('/docs/install'));
        $this->assertTrue($user->mayRead('/docs/install/'));
        $this->assertFalse($user->mayCreate('/docs'));
        $this->assertFalse($user->mayCreate('/docs/'));
        $this->assertFalse($user->mayCreate('/docs/install'));
        $this->assertFalse($user->mayCreate('/docs/install/'));
        $this->assertFalse($user->mayUpdate('/docs'));
        $this->assertFalse($user->mayUpdate('/docs/'));
        $this->assertFalse($user->mayUpdate('/docs/install'));
        $this->assertFalse($user->mayUpdate('/docs/install/'));
        $this->assertFalse($user->mayDelete('/docs'));
        $this->assertFalse($user->mayDelete('/docs/'));
        $this->assertFalse($user->mayDelete('/docs/install'));
        $this->assertFalse($user->mayDelete('/docs/install/'));
    }

    public function testDocsUser(): void
    {
        // the 'docs' user is allowed to do everything one subdir, but not in others
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);

        $this->assertFalse($user->hasExplicitPermission('docs', 'userCreate', '/docs')); // a page in the root folder!
        $this->assertTrue($user->hasExplicitPermission('docs', 'userCreate', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userCreate', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userCreate', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userCreate', '/docs/more/infos/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/docs/more/infos/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userUpdate', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userUpdate', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userUpdate', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userUpdate', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userUpdate', '/docs/more/infos/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userDelete', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userDelete', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userDelete', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userDelete', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userDelete', '/docs/more/infos/'));

        $this->assertFalse($user->hasExplicitPermission('docs', 'userCreate', '/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userCreate', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userCreate', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userCreate', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userCreate', '/somefolder/somefolder/somepage'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/somepage'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/somefolder/'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/somefolder/somepage'));
        $this->assertTrue($user->hasExplicitPermission('docs', 'userRead', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userUpdate', '/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userUpdate', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userUpdate', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userUpdate', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userUpdate', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userDelete', '/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userDelete', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userDelete', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userDelete', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('docs', 'userDelete', '/somefolder/somefolder/somepage'));
    }
}

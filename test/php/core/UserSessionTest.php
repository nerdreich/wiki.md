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

    public function testAnonymoususer(): void
    {
        // anonymous users should only be able to read stuff

        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config); // this will be the anonymous user

        $this->assertFalse($user->mayRead(''));
        $this->assertFalse($user->mayCreate(''));
        $this->assertFalse($user->mayUpdate(''));
        $this->assertFalse($user->mayDelete(''));
        $this->assertFalse($user->mayAdmin(''));

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
        $this->assertFalse($user->mayAdmin('/'));
        $this->assertFalse($user->mayAdmin('/somepage'));
        $this->assertFalse($user->mayAdmin('/somefolder/'));
        $this->assertFalse($user->mayAdmin('/somefolder/somepage'));
        $this->assertFalse($user->mayAdmin('/somefolder/somefolder/somepage'));

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
        $this->assertFalse($user->mayAdmin('/docs'));
        $this->assertFalse($user->mayAdmin('/docs/'));
        $this->assertFalse($user->mayAdmin('/docs/install'));
        $this->assertFalse($user->mayAdmin('/docs/install/'));
    }

    public function testDocsUser(): void
    {
        // the 'docs' user is allowed to do everything one subdir, but not in others
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'docs'); // pseudo-login

        $this->assertFalse($user->hasExplicitPermission('userCreate', '/docs')); // a page in the root folder!
        $this->assertTrue($user->hasExplicitPermission('userCreate', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('userCreate', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('userCreate', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('userCreate', '/docs/more/infos/'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/docs/more/infos/'));
        $this->assertFalse($user->hasExplicitPermission('userUpdate', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('userUpdate', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('userUpdate', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('userUpdate', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('userUpdate', '/docs/more/infos/'));
        $this->assertFalse($user->hasExplicitPermission('userDelete', '/docs'));
        $this->assertTrue($user->hasExplicitPermission('userDelete', '/docs/'));
        $this->assertTrue($user->hasExplicitPermission('userDelete', '/docs/install'));
        $this->assertTrue($user->hasExplicitPermission('userDelete', '/docs/more/infos'));
        $this->assertTrue($user->hasExplicitPermission('userDelete', '/docs/more/infos/'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/docs'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/docs/'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/docs/install'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/docs/more/infos'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/docs/more/infos/'));

        $this->assertFalse($user->hasExplicitPermission('userCreate', '/'));
        $this->assertFalse($user->hasExplicitPermission('userCreate', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userCreate', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('userCreate', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userCreate', '/somefolder/somefolder/somepage'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/somepage'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/somefolder/'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/somefolder/somepage'));
        $this->assertTrue($user->hasExplicitPermission('userRead', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userUpdate', '/'));
        $this->assertFalse($user->hasExplicitPermission('userUpdate', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userUpdate', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('userUpdate', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userUpdate', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userDelete', '/'));
        $this->assertFalse($user->hasExplicitPermission('userDelete', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userDelete', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('userDelete', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userDelete', '/somefolder/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/somefolder/'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/somefolder/somepage'));
        $this->assertFalse($user->hasExplicitPermission('userAdmin', '/somefolder/somefolder/somepage'));
    }

    public function testUserAdmin(): void
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);

        $hash = hash('sha1', file_get_contents(self::$htpasswd));
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, '*');
        $methodLogin = $this->getAsPublicMethod('\at\nerdreich\UserSession', 'getUserForPassword');

        // no permissions -> no data
        $data = $user->adminFolder('/');
        $this->assertEquals(null, $data);
        $this->assertFalse($user->addSecret('docs', '*****'));
        $this->assertFalse($user->deleteUser('docs'));

        // user docs can't read/update it
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'docs');
        $data = $user->adminFolder('/');
        $this->assertEquals(null, $data);
        $this->assertFalse($user->addSecret('docs', '*****'));
        $this->assertFalse($user->deleteUser('docs'));

        // user admin can read it
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'admin');
        $data = $user->adminFolder('/');
        $this->assertCount(2, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertNotNull($methodLogin->invokeArgs($user, ['adm']));
        $this->assertNotNull($methodLogin->invokeArgs($user, ['doc']));

        // admin can add
        $this->assertTrue($user->addSecret('woof', '12345678'));
        $data = $user->adminFolder('/');
        $this->assertCount(3, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertContains('woof', $data['users']);
        $this->assertNotEquals($hash, hash('sha1', file_get_contents(self::$htpasswd))); // was written to db
        $this->assertNotNull($methodLogin->invokeArgs($user, ['adm']));
        $this->assertNotNull($methodLogin->invokeArgs($user, ['doc']));
        $this->assertNotNull($methodLogin->invokeArgs($user, ['12345678']));

        // admin can delete
        $this->assertTrue($user->deleteUser('woof'));
        $data = $user->adminFolder('/');
        $this->assertCount(2, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertNotNull($methodLogin->invokeArgs($user, ['adm']));
        $this->assertNotNull($methodLogin->invokeArgs($user, ['doc']));
        $this->assertNull($methodLogin->invokeArgs($user, ['12345678']));

        // can't delete superuser
        $this->assertFalse($user->deleteUser($user->getSuperuser()));

        // remaining password file should be back to the beginning
        $this->assertEquals($hash, hash('sha1', file_get_contents(self::$htpasswd)));

        // admin can change existing
        $this->assertTrue($user->addSecret('docs', '12345678'));
        $data = $user->adminFolder('/');
        $this->assertCount(2, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertNotEquals($hash, hash('sha1', file_get_contents(self::$htpasswd)));
        $this->assertNull($methodLogin->invokeArgs($user, ['doc']));
        $this->assertNotNull($methodLogin->invokeArgs($user, ['12345678']));
        $this->assertNotNull($methodLogin->invokeArgs($user, ['adm']));

        // assert user validity
        $this->assertTrue($user->addSecret('Docs', '123456'));  // ok
        $this->assertFalse($user->addSecret('1', '123456')); // user too short
        $this->assertFalse($user->addSecret('123456789012345678901234567890123', '123456')); // user too long
        $this->assertFalse($user->addSecret('Do cs', '123456')); // user has whitespace
        $this->assertFalse($user->addSecret(' Docs', '123456')); // user has whitespace
        $this->assertFalse($user->addSecret('Docs ', '123456')); // user has whitespace
        $this->assertFalse($user->addSecret('Docs ', '123456')); // user has whitespace
        $this->assertFalse($user->addSecret('D0cs', '123456'));  // user has non-letter
        $this->assertFalse($user->addSecret('Döcs', '123456'));  // user has non-letter

        // assert pwd validity
        $this->assertFalse($user->addSecret('Docs', '12345'));   // pwd too short
        $this->assertFalse($user->addSecret('Docs', '12345678901234567890123456789012345678901234567890' .
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789'));   // pwd too long
        $this->assertFalse($user->addSecret('Docs', '123 456')); // pwd has whitespace
        $this->assertFalse($user->addSecret('Docs', '123456 ')); // pwd has whitespace
        $this->assertFalse($user->addSecret('Docs', ' 123456')); // pwd has whitespace
    }

    public function testPermissionAdmin(): void
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config);
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, '*');

        // anonymous can't set permissions
        $this->assertFalse(
            $user->setPermissions('/some/test/folder/', ['admin'], ['admin'], ['admin'], ['admin'], ['admin'], ['admin'])
        );

        // non-admin user can't set permissions
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'docs');
        $this->assertFalse(
            $user->setPermissions('/some/test/folder/', ['admin'], ['admin'], ['admin'], ['admin'], ['admin'], ['admin'])
        );

        // admin can set permissions
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'admin');
        $this->assertTrue(
            $user->setPermissions('/some/test/folder/', ['admin'], ['admin'], ['admin'], ['admin'], ['admin'], ['admin'])
        );
        $this->assertFalse(
            $user->setPermissions('/some/test/folder', ['admin'], ['admin'], ['admin'], ['admin'], ['admin'], ['admin'])
        ); // even admin can't set permissions on a file

        // check for auto-correction of various element combinations
        $user->setPermissions(
            '/some/test/folder/',
            ['admin', 'docs'],
            ['*'],
            ['admin', '*', 'docs'],
            ['docs', 'someone', 'admin', 'unknown'],
            ['*', '*'],
            ['admin', 'admin']
        );
        $permissions = $this->getAsPublicMethod('\at\nerdreich\UserSession', 'loadPermissionFile')->invokeArgs($user, ['/some/test/folder/']);
        $this->assertEquals('admin,docs', $permissions['userCreate']);
        $this->assertEquals('*', $permissions['userRead']);
        $this->assertEquals('*', $permissions['userUpdate']);
        $this->assertEquals('admin,docs', $permissions['userDelete']);
        $this->assertEquals('*', $permissions['userMedia']);
        $this->assertEquals('admin', $permissions['userAdmin']);
    }
}

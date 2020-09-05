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
        $user = new UserSession($config['datafolder'], $config['login_simple']); // this will be the anonymous user

        $this->assertFalse($user->mayAdmin(''));

        $this->assertFalse($user->mayAdmin('/'));
        $this->assertFalse($user->mayAdmin('/somepage'));
        $this->assertFalse($user->mayAdmin('/somefolder/'));
        $this->assertFalse($user->mayAdmin('/somefolder/somepage'));
        $this->assertFalse($user->mayAdmin('/somefolder/somefolder/somepage'));

        $this->assertFalse($user->mayAdmin('/docs'));
        $this->assertFalse($user->mayAdmin('/docs/'));
        $this->assertFalse($user->mayAdmin('/docs/install'));
        $this->assertFalse($user->mayAdmin('/docs/install/'));
    }

    public function testDocsUser(): void
    {
        // the 'docs' user is allowed to do everything one subdir, but not in others
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config['datafolder'], $config['login_simple']);
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'docs'); // pseudo-login

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

    public function testUserAdmin(): void
    {
        $config = parse_ini_file('dist/wiki.md/data/config.ini');
        $user = new UserSession($config['datafolder'], $config['login_simple']);

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
        $user = new UserSession($config['datafolder'], $config['login_simple']);
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, '*');

        // anonymous can't set permissions
        $this->assertFalse(
            $user->setPermissions(
                '/some/test/folder/',
                [
                    'pageCreate' => ['admin'],
                    'pageRead' => ['admin'],
                    'pageUpdate' => ['admin'],
                    'pageDelete' => ['admin'],
                    'mediaAdmin' => ['admin'],
                    'userAdmin' => ['admin']
                ]
            )
        );

        // non-admin user can't set permissions
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'docs');
        $this->assertFalse(
            $user->setPermissions(
                '/some/test/folder/',
                [
                    'pageCreate' => ['admin'],
                    'pageRead' => ['admin'],
                    'pageUpdate' => ['admin'],
                    'pageDelete' => ['admin'],
                    'mediaAdmin' => ['admin'],
                    'userAdmin' => ['admin']
                ]
            )
        );

        // admin can set permissions
        $this->getPrivateProperty('\at\nerdreich\UserSession', 'username')->setValue($user, 'admin');
        $this->assertTrue(
            $user->setPermissions(
                '/some/test/folder/',
                [
                    'pageCreate' => ['admin'],
                    'pageRead' => ['admin'],
                    'pageUpdate' => ['admin'],
                    'pageDelete' => ['admin'],
                    'mediaAdmin' => ['admin'],
                    'userAdmin' => ['admin']
                ]
            )
        );
        $this->assertFalse(
            $user->setPermissions(
                '/some/test/page',
                [
                    'pageCreate' => ['admin'],
                    'pageRead' => ['admin'],
                    'pageUpdate' => ['admin'],
                    'pageDelete' => ['admin'],
                    'mediaAdmin' => ['admin'],
                    'userAdmin' => ['admin']
                ]
            )
        ); // even admin can't set permissions on a file

        // check for auto-correction of various element combinations
        $user->setPermissions(
            '/some/test/folder/',
            [
                'pageCreate' => ['admin', 'docs'],
                'pageRead' => ['*'],
                'pageUpdate' => ['admin', '*', 'docs'],
                'pageDelete' => ['docs', 'someone', 'admin', 'unknown'],
                'mediaAdmin' => ['*', '*'],
                'userAdmin' => ['admin', 'admin']
            ]
        );
        $permissions = $this->getAsPublicMethod('\at\nerdreich\UserSession', 'loadPermissionFile')
            ->invokeArgs($user, ['/some/test/folder/']);
        $this->assertEquals('admin,docs', $permissions['pageCreate']);
        $this->assertEquals('*', $permissions['pageRead']);
        $this->assertEquals('*', $permissions['pageUpdate']);
        $this->assertEquals('admin,docs', $permissions['pageDelete']);
        $this->assertEquals('*', $permissions['mediaAdmin']);
        $this->assertEquals('admin', $permissions['userAdmin']);
    }
}

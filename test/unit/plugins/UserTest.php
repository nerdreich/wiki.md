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

require_once('test/unit/WikiTestCase.php');

final class UserTest extends WikiTestCase
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

        $wiki = $this->getNewWikiUI('/test');
        $plugin = $wiki->core->getPlugin('user');

        $this->assertFalse($plugin->mayUserAdmin(''));

        $this->assertFalse($plugin->mayUserAdmin('/'));
        $this->assertFalse($plugin->mayUserAdmin('/somepage'));
        $this->assertFalse($plugin->mayUserAdmin('/somefolder/'));
        $this->assertFalse($plugin->mayUserAdmin('/somefolder/somepage'));
        $this->assertFalse($plugin->mayUserAdmin('/somefolder/somefolder/somepage'));

        $this->assertFalse($plugin->mayUserAdmin('/docs'));
        $this->assertFalse($plugin->mayUserAdmin('/docs/'));
        $this->assertFalse($plugin->mayUserAdmin('/docs/install'));
        $this->assertFalse($plugin->mayUserAdmin('/docs/install/'));
    }

    public function testUserAdmin(): void
    {
        $wiki = $this->getNewWikiUI('/test');
        $plugin = $wiki->core->getPlugin('user');

        $hash = hash('sha1', file_get_contents(self::$htpasswd));
        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($plugin->user, '*');
        $methodLogin = $this->getAsPublicMethod('\at\nerdreich\wiki\UserSession', 'getUserForPassword');

        // no permissions -> no data
        $data = $plugin->list('/');
        $this->assertEquals(null, $data);
        $this->assertFalse($plugin->addSecret('docs', '*****'));
        $this->assertFalse($plugin->deleteUser('docs'));

        // user docs can't read/update it
        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($plugin->user, 'docs');
        $data = $plugin->list('/');
        $this->assertEquals(null, $data);
        $this->assertFalse($plugin->addSecret('docs', '*****'));
        $this->assertFalse($plugin->deleteUser('docs'));

        // user admin can read it
        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($plugin->user, 'admin');
        $data = $plugin->list('/');
        $this->assertCount(2, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['adm']));
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['doc']));

        // admin can add
        $this->assertTrue($plugin->addSecret('woof', '12345678'));
        $data = $plugin->list('/');
        $this->assertCount(3, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertContains('woof', $data['users']);
        $this->assertNotEquals($hash, hash('sha1', file_get_contents(self::$htpasswd))); // was written to db
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['adm']));
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['doc']));
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['12345678']));

        // admin can delete
        $this->assertTrue($plugin->deleteUser('woof'));
        $data = $plugin->list('/');
        $this->assertCount(2, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['adm']));
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['doc']));
        $this->assertNull($methodLogin->invokeArgs($plugin->user, ['12345678']));

        // can't delete superuser
        $this->assertFalse($plugin->deleteUser($plugin->user->getSuperuser()));

        // remaining password file should be back to the beginning
        $this->assertEquals($hash, hash('sha1', file_get_contents(self::$htpasswd)));

        // admin can change existing
        $this->assertTrue($plugin->addSecret('docs', '12345678'));
        $data = $plugin->list('/');
        $this->assertCount(2, $data['users']);
        $this->assertContains('admin', $data['users']);
        $this->assertContains('docs', $data['users']);
        $this->assertNotEquals($hash, hash('sha1', file_get_contents(self::$htpasswd)));
        $this->assertNull($methodLogin->invokeArgs($plugin->user, ['doc']));
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['12345678']));
        $this->assertNotNull($methodLogin->invokeArgs($plugin->user, ['adm']));

        // assert user validity
        $this->assertTrue($plugin->addSecret('Docs', '123456'));  // ok
        $this->assertFalse($plugin->addSecret('1', '123456')); // user too short
        $this->assertFalse($plugin->addSecret('123456789012345678901234567890123', '123456')); // user too long
        $this->assertFalse($plugin->addSecret('Do cs', '123456')); // user has whitespace
        $this->assertFalse($plugin->addSecret(' Docs', '123456')); // user has whitespace
        $this->assertFalse($plugin->addSecret('Docs ', '123456')); // user has whitespace
        $this->assertFalse($plugin->addSecret('Docs ', '123456')); // user has whitespace
        $this->assertFalse($plugin->addSecret('D0cs', '123456'));  // user has non-letter
        $this->assertFalse($plugin->addSecret('Döcs', '123456'));  // user has non-letter

        // assert pwd validity
        $this->assertFalse($plugin->addSecret('Docs', '12345'));   // pwd too short
        $this->assertFalse($plugin->addSecret('Docs', '12345678901234567890123456789012345678901234567890' .
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789'));   // pwd too long
        $this->assertFalse($plugin->addSecret('Docs', '123 456')); // pwd has whitespace
        $this->assertFalse($plugin->addSecret('Docs', '123456 ')); // pwd has whitespace
        $this->assertFalse($plugin->addSecret('Docs', ' 123456')); // pwd has whitespace
    }

    public function testPermissionAdmin(): void
    {
        $wiki = $this->getNewWikiUI('/test');
        $plugin = $wiki->core->getPlugin('user');

        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($plugin->user, '*');

        // anonymous can't set permissions
        $this->assertFalse(
            $plugin->setPermissions(
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
        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($plugin->user, 'docs');
        $this->assertFalse(
            $plugin->setPermissions(
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
        $this->getPrivateProperty('\at\nerdreich\wiki\UserSession', 'username')->setValue($plugin->user, 'admin');
        $this->assertTrue(
            $plugin->setPermissions(
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
            $plugin->setPermissions(
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
        $plugin->setPermissions(
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
        $permissions = $this->getAsPublicMethod('\at\nerdreich\wiki\UserSession', 'loadPermissionFileFS')
            ->invokeArgs($plugin->user, ['/some/test/folder/']);
        $this->assertEquals('admin,docs', $permissions['pageCreate']);
        $this->assertEquals('*', $permissions['pageRead']);
        $this->assertEquals('*', $permissions['pageUpdate']);
        $this->assertEquals('admin,docs', $permissions['pageDelete']);
        $this->assertEquals('*', $permissions['mediaAdmin']);
        $this->assertEquals('admin', $permissions['userAdmin']);
    }
}

<?php

/**
 * Copyright 2020 Markus Leupold-Löwenthal
 *
 * This file is part of wiki.md.
 *
 * wiki.md is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option);any
 * later version.
 *
 * wiki.md is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with wiki.md. If not, see <https://www.gnu.org/licenses/>.
 */

namespace at\nerdreich;

require 'IntegrationTestCase.php';

/**
 * Run wiki.md's integration tests via HTTP/curl.
 *
 * This clas runs integration tests - mainly a CRUD lifecycle roundtrip for a
 * wiki page. This assumes that you have the content of `dist/wiki.md` served
 * via a local httpd and its URL in $SERVER (default https://wiki.local).
 *
 * Steps to run it:
 * * `gulp dist`
 * * launch webserver
 * * `tools/phpunit-9.phar test/integration`
 *
 * Warning: This makes destructive calls to wiki pages and erases passwords.
 * Do only run it against test instances.
 */
class IntegrationTest extends IntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // set logindata to known passwords (adm, doc)
        file_put_contents(
            dirname(__FILE__) . '/../../dist/wiki.md/data/.htpasswd',
            'admin:$2y$05$mcqOIM9K4lZfujCONaP7yu32/L5Ptzndf2xRN1/3EMO/UM7qicl8i' . PHP_EOL .
            'docs:$2y$05$KiA/6HVXZ6sQsY9c8.0j/.g6HjBHwrV8lmLvlvxo76EdeIbOzgyBq' . PHP_EOL
        );
    }

    protected function setUp(): void
    {
        // always start logged-out
        $this->reset();
    }

    public function testAnonymousExistingPages(): void
    {
        $this->get('/');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/Welcome!/');

        $this->get('/?action=unknown');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/Welcome!/');

        $this->get('/?action=createPage');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/?action=edit');
        $this->assertPageLogin();
        $this->assertNoCookies();

        $this->get('/?action=history');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/History for/');

        $this->get('/?action=delete');
        $this->assertPageLogin();
        $this->assertNoCookies();

        $this->get('/?action=deleteOK');
        $this->assertPageLogin();
        $this->assertNoCookies();

        $this->get('/?action=restore&version=0');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/?action=restore&version=1');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/?auth=login');
        $this->assertPageLogin();
        $this->assertNoCookies();

        $this->get('/?auth=logout');
        $this->assertRedirect('/');
        $this->assertNoCookies();

        $this->get('/?auth=unknown');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/Welcome!/');
    }

    public function testAnonymousNonExistingPages(): void
    {
        $this->get('/meow');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=unknown');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=createPage');
        $this->assertPageLogin();
        $this->assertNoCookies();

        $this->get('/meow?action=edit');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=history');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=delete');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=deleteOK');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=restore&version=0');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?action=restore&version=1');
        $this->assertPageNotFound();
        $this->assertNoCookies();
    }

    public function testLoginLogout(): void
    {
        $this->get('/');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/<a href="\?auth=login">/');        // login button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=edit">/');    // edit button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=history">/'); // history button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=delete">/');  // delete button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=logout">/');  // logout button

        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        $this->get('/');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsNotPreg('/<a href="\?auth=login">/');     // login button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=edit">/');    // edit button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=history">/'); // history button
        $this->assertPayloadContainsNotPreg('/<a href="\?action=delete">/');  // delete button
        $this->assertPayloadContainsPreg('/<a href="\?auth=logout">/');       // logout button
        $this->assertPayloadContainsPreg('/"page"/');                         // css

        $this->get('/docs/install');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsNotPreg('/<a href="\?auth=login">/');  // login button
        $this->assertPayloadContainsPreg('/<a href="\?action=edit">/');    // edit button
        $this->assertPayloadContainsPreg('/<a href="\?action=history">/'); // history button
        $this->assertPayloadContainsPreg('/<a href="\?action=delete">/');  // delete button
        $this->assertPayloadContainsPreg('/<a href="\?auth=logout">/');    // logout button
        $this->assertPayloadContainsPreg('/"page page-docs page-docs-install"/');   // css

        $this->get('/?auth=logout');
        $this->assertRedirect('/');
        $this->assertNoCookies();

        $this->get('/');
        $this->assertPage();
        $this->assertNoCookies();

        $this->post('/?auth=login', ['password' => 'invalid']);
        $this->assertPageLogin();
        $this->assertNoCookies();
    }

    public function testLoginLogin(): void
    {
        // first login
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $token1 = $this->assertSessionCookie();

        // login again - must be new session
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $token2 = $this->assertSessionCookie();
        $this->assertNotEquals($token1, $token2);

        // login as other user - must be new session
        $this->post('/?auth=login', ['password' => 'adm']);
        $this->assertRedirect('/');
        $token3 = $this->assertSessionCookie();
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token1, $token3);
        $this->assertNotEquals($token2, $token3);
    }

    public function testDocsCRUD(): void
    {
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        $this->get('/docs/crud');
        $this->assertPageNotFound();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="Create page"/'); // create button

        $this->get('/docs/crud?action=createPage');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/textarea id="content"/'); // editor

        $this->post('/docs/crud?action=save', [
            'title' => 'first title',
            'content' => 'first save',
            'author' => 'first author'
        ]);
        $this->assertRedirect('/docs/crud');
        $this->assertSessionCookie();

        $this->get('/docs/crud');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>first title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>first save<\/p>/');

        $this->get('/docs/crud?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');
        $this->assertSessionCookie();

        $this->get('/docs/crud?action=edit');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="first title"/');// editor
        $this->assertPayloadContainsPreg('/>first save</');// editor
        $this->assertPayloadContainsPreg('/value="first author"/'); // editor

        $this->post('/docs/crud?action=save', [
            'title' => 'second title',
            'content' => 'second save',
            'author' => 'second author'
        ]);
        $this->assertRedirect('/docs/crud');
        $this->assertSessionCookie();

        $this->get('/docs/crud');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>second title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>second save<\/p>/');

        $this->get('/docs/crud?action=delete');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>second title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>second save<\/p>/');
        $this->assertPayloadContainsPreg('/value="Delete page"/'); // delete button

        $this->post('/docs/crud?action=deleteOK');
        $this->assertRedirect('/docs/crud');
        $this->assertSessionCookie();

        $this->get('/docs/crud');
        $this->assertPageNotFound();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="Create page"/'); // create button
    }

    public function testHistory(): void
    {
        // login
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');

        // nonexisiting page does not have history
        $this->get('/docs/meow_history?action=history');
        $this->assertPageNotFound();
        $this->assertPayloadContainsPreg('/value="Create page"/'); // create button

        // create page
        $this->post('/docs/meow_history?action=save', [
            'title' => 'first title',
            'content' => 'first save',
            'author' => 'first author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // one history entry
        $this->get('/docs/meow_history?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');

        // save again - same user
        $this->post('/docs/meow_history?action=save', [
            'title' => 'second title',
            'content' => 'second save',
            'author' => 'first author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // still one history entry - got squashed
        $this->get('/docs/meow_history?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');

        // save again - different user
        $this->post('/docs/meow_history?action=save', [
            'title' => 'third title',
            'content' => 'third save',
            'author' => 'second author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // two history entries
        $this->get('/docs/meow_history?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');

        // save again - no changes - yet another different user
        $this->post('/docs/meow_history?action=save', [
            'title' => '4th title',
            'content' => 'third save',
            'author' => 'third author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // still two history entries - no-diff change did not get saved
        $this->get('/docs/meow_history?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');

        // restore history - will return to second save as the first one was squashed
        $this->post('/docs/meow_history?action=restore&version=1');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="4th title"/');
        $this->assertPayloadContainsPreg('/>second save</');// only body gets restored
        $this->assertPayloadContainsPreg('/value="third author"/');

        // we did not save the revert, so no change to page
        $this->get('/docs/meow_history?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');

        // cleaup
        $this->post('/docs/meow_history?action=deleteOK');
    }

    public function testHistoryFilesystemChanges(): void
    {
        // test what happens to the history if direct changes in the filesystem
        // are made to .md files without using wiki.md's editor

        // load fs-created markdown file
        $this->get('/docs/snippets');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsNotPreg('/h1/');         // no meta title -> no h1
        $this->assertPayloadContainsNotPreg('/Last saved/'); // no meta date -> no footer info
        $this->assertPayloadContainsPreg('/<h2>Snippets<\/h2>/');

        // check history - no info there due lack of metadata
        $this->get('/docs/snippets?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-0"/');
        $this->assertPayloadContainsNotPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/page has not been saved by wiki.md/');
        $this->assertPayloadContainsPreg('/No history is available/');

        // login
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');

        // normal save page
        $this->get('/docs/snippets?action=edit');
        $this->assertPage();
        $this->post('/docs/snippets?action=save', [
            'title' => 'snippets save 1',
            'content' => 'snippets 1',
            'author' => 'snippet 1'
        ]);
        $this->assertRedirect('/docs/snippets');

        // check history - now contains two entries, one for fs and one for snippet
        $this->get('/docs/snippets?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/by \?\?\? at/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsPreg('/by snippet 1 at/');
        $this->assertPayloadContainsNotPreg('/id="history-0"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');
        $this->assertPayloadContainsNotPreg('/page has not been saved by wiki.md/');
        $this->assertPayloadContainsNotPreg('/No history is available/');

        // do a fs change
        file_put_contents(
            dirname(__FILE__) . '/../../dist/wiki.md/data/content/docs/snippets.md',
            PHP_EOL . 'fs change ' . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        // check history - still contains two entries, but also the dirty warning again
        $this->get('/docs/snippets?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/by \?\?\? at/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsPreg('/by snippet 1 at/');
        $this->assertPayloadContainsNotPreg('/id="history-0"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');
        $this->assertPayloadContainsPreg('/The checksum of this page is invalid./');

        // edit & save again
        $this->get('/docs/snippets?action=edit');
        $this->assertPage();
        $this->post('/docs/snippets?action=save', [
            'title' => 'snippets save 2' ,
            'content' => 'snippets 2',
            'author' => 'snippet 2'
        ]);
        $this->assertRedirect('/docs/snippets');

        // check history - now contains 4 entries, two for fs and two for snippet
        $this->get('/docs/snippets?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/by \?\?\? at/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsPreg('/by snippet 1 at/');
        $this->assertPayloadContainsPreg('/by snippet 2 at/');
        $this->assertPayloadContainsPreg('/id="history-3"/');
        $this->assertPayloadContainsPreg('/id="history-4"/');
        $this->assertPayloadContainsNotPreg('/id="history-5"/');
        $this->assertPayloadContainsNotPreg('/id="history-0"/');

        // we could go back to version 3
        $this->post('/docs/snippets?action=restore&version=3');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="snippets save 2"/');
        $this->assertPayloadContainsPreg('/>snippets 1/');
        $this->assertPayloadContainsPreg('/value="snippet 2"/');

        // but history is broken for 2 and 1
        $this->post('/docs/snippets?action=restore&version=2');
        $this->assertPageError();
        $this->post('/docs/snippets?action=restore&version=1');
        $this->assertPageError();
    }

    public function testEditor(): void
    {
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // create a test page
        $this->post('/docs/meow?action=save', [
            'title' => 'meow save',
            'content' => 'meow',
            'author' => 'cat'
        ]);

        // put first page in edit mode
        $this->get('/docs/meow?action=edit');
        $this->assertPage();
        $this->assertPayloadContainsNotPreg('/Someone started editing/');
        sleep(1);

        // switch user
        $this->post('/?auth=login', ['password' => 'adm']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // now we should get the warning
        $this->get('/docs/meow?action=edit');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/Someone started editing/');

        // we save anyway
        $this->post('/docs/meow?action=save', [
            'title' => 'meow save 2',
            'content' => 'meow meow',
            'author' => 'cat'
        ]);
        $this->assertRedirect('/docs/meow');

        // warning gone
        $this->get('/docs/meow?action=edit');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="meow save 2"/');
        $this->assertPayloadContainsPreg('/>meow meow</');
        $this->assertPayloadContainsPreg('/value="cat"/');
        $this->assertPayloadContainsNotPreg('/Someone started editing/');

        // cleaup
        $this->post('/docs/meow?action=deleteOK');
        $this->post('/docs/woof?action=deleteOK');
    }

    public function testPermissionEditor(): void
    {
        $this->post('/?auth=login', ['password' => 'adm']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // docs default permissions
        $this->get('/docs/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/name="userCreate" value="docs"/');
        $this->assertPayloadContainsPreg('/name="userRead" value=""/');
        $this->assertPayloadContainsPreg('/name="userUpdate" value="docs" /');
        $this->assertPayloadContainsPreg('/name="userDelete" value="docs"/');
        $this->assertPayloadContainsPreg('/name="userAdmin" value=""/');

        // set permissions on a test folder
        $this->post('/docs/perms/?admin=permissions', [
            'userCreate' => 'admin',
            'userRead' => 'docs,admin',
            'userUpdate' => 'admin,docs',
            'userDelete' => '',
            'userAdmin' => '*'
        ]);
        $this->assertRedirect('/docs/perms/?admin=folder');

        // check changes
        $this->get('/docs/perms/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/name="userCreate" value="admin"/');
        $this->assertPayloadContainsPreg('/name="userRead" value="admin,docs"/');
        $this->assertPayloadContainsPreg('/name="userUpdate" value="admin,docs" /');
        $this->assertPayloadContainsPreg('/name="userDelete" value=""/');
        $this->assertPayloadContainsPreg('/name="userAdmin" value="\*"/');

        // set edge cases
        $this->post('/docs/perms/?admin=permissions', [
            // no create - 'userCreate' => null,
            'userRead' => ' docs',
            'userUpdate' => null,
            'userDelete' => 'docs,',
            'userAdmin' => ',docs, docs'
        ]);
        $this->assertRedirect('/docs/perms/?admin=folder');

        // check changes
        $this->get('/docs/perms/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/name="userCreate" value=""/');
        $this->assertPayloadContainsPreg('/name="userRead" value="docs"/');
        $this->assertPayloadContainsPreg('/name="userUpdate" value="" /');
        $this->assertPayloadContainsPreg('/name="userDelete" value="docs"/');
        $this->assertPayloadContainsPreg('/name="userAdmin" value="docs"/');
    }

    public function testUserEditor(): void
    {
        $this->post('/?auth=login', ['password' => 'adm']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // docs default users
        $this->get('/docs/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li><strong>admin</');
        $this->assertPayloadContainsPreg('/<li><strong>docs</');

        // create a user
        $this->post('/docs/?admin=secret', [
            'username' => 'nr',
            'secret' => 'supersecret',
        ]);
        $this->assertRedirect('/docs/?admin=folder');
        $this->get('/docs/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li><strong>admin</');
        $this->assertPayloadContainsPreg('/<li><strong>docs</');
        $this->assertPayloadContainsPreg('/<li><strong>nr</');

        // update a user
        $this->post('/docs/?admin=secret', [
            'username' => 'nr',
            'secret' => 'supersecret2',
        ]);
        $this->assertRedirect('/docs/?admin=folder');
        $this->get('/docs/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li><strong>admin</');
        $this->assertPayloadContainsPreg('/<li><strong>docs</');
        $this->assertPayloadContainsPreg('/<li><strong>nr</');

        // delete a user
        $this->get('/docs/?admin=delete&user=nr');
        $this->assertRedirect('/docs/?admin=folder');
        $this->get('/docs/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li><strong>admin</');
        $this->assertPayloadContainsPreg('/<li><strong>docs</');
        $this->assertPayloadContainsNotPreg('/<li><strong>nr</');

        // delete again/invalid
        $this->get('/docs/?admin=delete&user=nr');
        $this->assertPage();
        $this->get('/docs/?admin=delete&user=');
        $this->assertPage();
        $this->get('/docs/?admin=delete&user=*');
        $this->assertPage();
        $this->get('/docs/?admin=folder');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li><strong>admin</');
        $this->assertPayloadContainsPreg('/<li><strong>docs</');
        $this->assertPayloadContainsNotPreg('/<li><strong>nr</');
    }
}

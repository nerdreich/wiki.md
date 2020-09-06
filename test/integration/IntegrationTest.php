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

namespace at\nerdreich\wiki;

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

        $this->get('/?page=unknown');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/Welcome!/');

        $this->get('/?page=create'); // not a route for exisiting pages
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/Welcome!/');

        $this->get('/?page=edit');
        $this->assertPageLogin(); // transparent login
        $this->assertNoCookies();

        $this->get('/?page=history');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/History for/');

        $this->get('/?page=delete');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/?page=deleteOK');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/?page=restore&version=0');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/?page=restore&version=1');
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

        $this->get('/meow?page=unknown');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?page=create');
        $this->assertPageError();
        $this->assertNoCookies();

        $this->get('/meow?page=edit');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?page=history');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?page=delete');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?page=deleteOK');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?page=restore&version=0');
        $this->assertPageNotFound();
        $this->assertNoCookies();

        $this->get('/meow?page=restore&version=1');
        $this->assertPageNotFound();
        $this->assertNoCookies();
    }

    public function testLoginLogout(): void
    {
        // verify non-simple login mode
        $cfgOrig = file_get_contents(dirname(__FILE__) . '/../../dist/wiki.md/data/config.ini');
        $this->assertTrue(strpos('login_simple = false', $cfgOrig) >= 0);
        $this->assertFalse(strpos('login_simple = true', $cfgOrig));

        $this->get('/');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/<a href="\?auth=login">/');      // login button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=edit">/');    // edit button
        $this->assertPayloadContainsNotPreg('/<a href="\?media=list">/');   // media button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=history">/'); // history button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=delete">/');  // delete button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=logout">/');  // logout button

        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        $this->get('/');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsNotPreg('/<a href="\?auth=login">/');   // login button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=edit">/');    // edit button
        $this->assertPayloadContainsNotPreg('/<a href="\?media=list">/');   // media button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=history">/'); // history button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=delete">/');  // delete button
        $this->assertPayloadContainsPreg('/<a href="\?auth=logout">/');     // logout button
        $this->assertPayloadContainsPreg('/"page"/');                       // css

        $this->get('/docs/install');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsNotPreg('/<a href="\?auth=login">/'); // login button
        $this->assertPayloadContainsPreg('/<a href="\?page=edit">/');     // edit button
        $this->assertPayloadContainsPreg('/<a href="\?media=list">/');    // media button
        $this->assertPayloadContainsPreg('/<a href="\?page=history">/');  // history button
        $this->assertPayloadContainsPreg('/<a href="\?page=delete">/');   // delete button
        $this->assertPayloadContainsPreg('/<a href="\?auth=logout">/');   // logout button
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

    public function testLoginLogoutSimple(): void
    {
        // switch to simple mode
        $cfgOrig = file_get_contents(dirname(__FILE__) . '/../../dist/wiki.md/data/config.ini');
        $cfg = str_replace('login_simple = false', 'login_simple = true', $cfgOrig);
        file_put_contents(
            dirname(__FILE__) . '/../../dist/wiki.md/data/config.ini',
            $cfg,
            FILE_APPEND | LOCK_EX
        );
        $cfg = file_get_contents(dirname(__FILE__) . '/../../dist/wiki.md/data/config.ini');
        $this->assertTrue(strpos('login_simple = true', $cfg) >= 0);
        $this->assertFalse(strpos('login_simple = false', $cfg));

        $this->get('/');
        $this->assertPage();
        $this->assertNoCookies();
        $this->assertPayloadContainsPreg('/<a href="\?auth=login">/');      // login button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=edit">/');    // edit button
        $this->assertPayloadContainsNotPreg('/<a href="\?media=list">/');   // media button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=history">/'); // history button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=delete">/');  // delete button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=logout">/');  // logout button

        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        $this->get('/');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsNotPreg('/<a href="\?auth=login">/');   // login button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=edit">/');    // edit button
        $this->assertPayloadContainsNotPreg('/<a href="\?media=list">/');   // media button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=history">/'); // history button
        $this->assertPayloadContainsNotPreg('/<a href="\?page=delete">/');  // delete button
        $this->assertPayloadContainsPreg('/<a href="\?auth=logout">/');     // logout button
        $this->assertPayloadContainsPreg('/"page"/');                       // css

        $this->get('/docs/install');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsNotPreg('/<a href="\?auth=login">/');  // login button
        $this->assertPayloadContainsPreg('/<a href="\?page=edit">/');      // edit button
        $this->assertPayloadContainsPreg('/<a href="\?media=list">/');     // media button
        $this->assertPayloadContainsPreg('/<a href="\?page=history">/');   // history button
        $this->assertPayloadContainsPreg('/<a href="\?page=delete">/');    // delete button
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

        // switch back to full mode
        file_put_contents(
            dirname(__FILE__) . '/../../dist/wiki.md/data/config.ini',
            $cfgOrig,
            FILE_APPEND | LOCK_EX
        );
    }

    public function testLoginLogin(): void
    {
        // first login
        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');
        $token1 = $this->assertSessionCookie();

        // login again - must be new session
        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');
        $token2 = $this->assertSessionCookie();
        $this->assertNotEquals($token1, $token2);

        // login as other user - must be new session
        $this->post('/?auth=login', ['username' => 'admin', 'password' => 'adm']);
        $this->assertRedirect('/');
        $token3 = $this->assertSessionCookie();
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token1, $token3);
        $this->assertNotEquals($token2, $token3);
    }

    public function testImplicitLogin(): void
    {
        // login
        $this->post('/docs/newpage?auth=login&page=create', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/docs/newpage?page=create');

        $this->get('/?auth=logout');
        $this->assertRedirect('/');

        $this->post('/docs/newpage?auth=login&media=list', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/docs/newpage?media=list');

        $this->get('/?auth=logout');
        $this->assertRedirect('/');
    }

    public function testDocsCRUD(): void
    {
        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        $this->get('/docs/crud');
        $this->assertPageNotFound();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="Create page"/'); // create button

        $this->get('/docs/crud?page=create');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/textarea id="content"/'); // editor

        $this->post('/docs/crud?page=save', [
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

        $this->get('/docs/crud?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');
        $this->assertSessionCookie();

        $this->get('/docs/crud?page=edit');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="first title"/');// editor
        $this->assertPayloadContainsPreg('/>first save</');// editor
        $this->assertPayloadContainsPreg('/value="first author"/'); // editor

        $this->post('/docs/crud?page=save', [
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

        $this->get('/docs/crud?page=delete');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>second title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>second save<\/p>/');
        $this->assertPayloadContainsPreg('/value="Delete page"/'); // delete button

        $this->post('/docs/crud?page=deleteOK');
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
        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');

        // nonexisiting page does not have history
        $this->get('/docs/meow_history?page=history');
        $this->assertPageNotFound();
        $this->assertPayloadContainsPreg('/value="Create page"/'); // create button

        // create page
        $this->post('/docs/meow_history?page=save', [
            'title' => 'first title',
            'content' => 'first save',
            'author' => 'first author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // one history entry
        $this->get('/docs/meow_history?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');

        // save again - same user
        $this->post('/docs/meow_history?page=save', [
            'title' => 'second title',
            'content' => 'second save',
            'author' => 'first author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // still one history entry - got squashed
        $this->get('/docs/meow_history?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');

        // save again - different user
        $this->post('/docs/meow_history?page=save', [
            'title' => 'third title',
            'content' => 'third save',
            'author' => 'second author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // two history entries
        $this->get('/docs/meow_history?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');

        // save again - no changes - yet another different user
        $this->post('/docs/meow_history?page=save', [
            'title' => '4th title',
            'content' => 'third save',
            'author' => 'third author'
        ]);
        $this->assertRedirect('/docs/meow_history');

        // still two history entries - no-diff change did not get saved
        $this->get('/docs/meow_history?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');

        // restore history - will return to second save as the first one was squashed
        $this->post('/docs/meow_history?page=restore&version=1');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="4th title"/');
        $this->assertPayloadContainsPreg('/>second save</');// only body gets restored
        $this->assertPayloadContainsPreg('/value="third author"/');

        // we did not save the revert, so no change to page
        $this->get('/docs/meow_history?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');

        // cleaup
        $this->post('/docs/meow_history?page=deleteOK');
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
        $this->get('/docs/snippets?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-0"/');
        $this->assertPayloadContainsNotPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/page has not been saved by wiki.md/');
        $this->assertPayloadContainsPreg('/No history is available/');

        // login
        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');

        // normal save page
        $this->get('/docs/snippets?page=edit');
        $this->assertPage();
        $this->post('/docs/snippets?page=save', [
            'title' => 'snippets save 1',
            'content' => 'snippets 1',
            'author' => 'snippet 1'
        ]);
        $this->assertRedirect('/docs/snippets');

        // check history - now contains two entries, one for fs and one for snippet
        $this->get('/docs/snippets?page=history');
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
        $this->get('/docs/snippets?page=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsPreg('/by \?\?\? at/');
        $this->assertPayloadContainsPreg('/id="history-2"/');
        $this->assertPayloadContainsPreg('/by snippet 1 at/');
        $this->assertPayloadContainsNotPreg('/id="history-0"/');
        $this->assertPayloadContainsNotPreg('/id="history-3"/');
        $this->assertPayloadContainsPreg('/The checksum of this page is invalid./');

        // edit & save again
        $this->get('/docs/snippets?page=edit');
        $this->assertPage();
        $this->post('/docs/snippets?page=save', [
            'title' => 'snippets save 2' ,
            'content' => 'snippets 2',
            'author' => 'snippet 2'
        ]);
        $this->assertRedirect('/docs/snippets');

        // check history - now contains 4 entries, two for fs and two for snippet
        $this->get('/docs/snippets?page=history');
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
        $this->post('/docs/snippets?page=restore&version=3');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="snippets save 2"/');
        $this->assertPayloadContainsPreg('/>snippets 1/');
        $this->assertPayloadContainsPreg('/value="snippet 2"/');

        // but history is broken for 2 and 1
        $this->post('/docs/snippets?page=restore&version=2');
        $this->assertPageError();
        $this->post('/docs/snippets?page=restore&version=1');
        $this->assertPageError();
    }

    public function testEditor(): void
    {
        $this->post('/?auth=login', ['username' => 'docs', 'password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // create a test page
        $this->post('/docs/meow?page=save', [
            'title' => 'meow save',
            'content' => 'meow',
            'author' => 'cat'
        ]);

        // put first page in edit mode
        $this->get('/docs/meow?page=edit');
        $this->assertPage();
        $this->assertPayloadContainsNotPreg('/Someone started editing/');
        sleep(1);

        // switch user
        $this->post('/?auth=login', ['username' => 'admin', 'password' => 'adm']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // now we should get the warning
        $this->get('/docs/meow?page=edit');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/Someone started editing/');

        // we save anyway
        $this->post('/docs/meow?page=save', [
            'title' => 'meow save 2',
            'content' => 'meow meow',
            'author' => 'cat'
        ]);
        $this->assertRedirect('/docs/meow');

        // warning gone
        $this->get('/docs/meow?page=edit');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="meow save 2"/');
        $this->assertPayloadContainsPreg('/>meow meow</');
        $this->assertPayloadContainsPreg('/value="cat"/');
        $this->assertPayloadContainsNotPreg('/Someone started editing/');

        // cleaup
        $this->post('/docs/meow?page=deleteOK');
        $this->post('/docs/woof?page=deleteOK');
    }

    public function testPermissionEditor(): void
    {
        $this->post('/?auth=login', ['username' => 'admin', 'password' => 'adm']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // docs default permissions
        $this->get('/docs/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/name="pageCreate" value="docs"/');
        $this->assertPayloadContainsPreg('/name="pageRead" value=""/');
        $this->assertPayloadContainsPreg('/name="pageUpdate" value="docs"/');
        $this->assertPayloadContainsPreg('/name="pageDelete" value="docs"/');
        $this->assertPayloadContainsPreg('/name="mediaAdmin" value="docs"/');
        $this->assertPayloadContainsPreg('/name="userAdmin" value=""/');

        // set permissions on a test folder
        $this->post('/docs/perms/?user=set', [
            'pageCreate' => 'admin',
            'pageRead' => 'docs,admin',
            'pageUpdate' => 'admin,docs',
            'pageDelete' => '',
            'mediaAdmin' => 'admin,admin',
            'userAdmin' => '*'
        ]);
        $this->assertRedirect('/docs/perms/?user=list');

        // check changes
        $this->get('/docs/perms/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/name="pageCreate" value="admin"/');
        $this->assertPayloadContainsPreg('/name="pageRead" value="admin,docs"/');
        $this->assertPayloadContainsPreg('/name="pageUpdate" value="admin,docs"/');
        $this->assertPayloadContainsPreg('/name="pageDelete" value=""/');
        $this->assertPayloadContainsPreg('/name="mediaAdmin" value="admin"/');
        $this->assertPayloadContainsPreg('/name="userAdmin" value="\*"/');

        // set edge cases
        $this->post('/docs/perms/?user=set', [
            // no create - 'pageCreate' => null,
            'pageRead' => ' docs',
            'pageUpdate' => null,
            'pageDelete' => 'docs,',
            // no upload - 'mediaAdmin' => null,
            'userAdmin' => ',docs, docs'
        ]);
        $this->assertRedirect('/docs/perms/?user=list');

        // check changes
        $this->get('/docs/perms/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/name="pageCreate" value=""/');
        $this->assertPayloadContainsPreg('/name="pageRead" value="docs"/');
        $this->assertPayloadContainsPreg('/name="pageUpdate" value=""/');
        $this->assertPayloadContainsPreg('/name="pageDelete" value="docs"/');
        $this->assertPayloadContainsPreg('/name="mediaAdmin" value=""/');
        $this->assertPayloadContainsPreg('/name="userAdmin" value="docs"/');
    }

    public function testUserEditor(): void
    {
        $this->post('/?auth=login', ['username' => 'admin', 'password' => 'adm']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // docs default users
        $this->get('/docs/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li>admin/');
        $this->assertPayloadContainsPreg('/<li>docs/');

        // create a user
        $this->post('/docs/?user=secret', [
            'username' => 'nr',
            'secret' => 'supersecret',
        ]);
        $this->assertRedirect('/docs/?user=list');
        $this->get('/docs/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li>admin/');
        $this->assertPayloadContainsPreg('/<li>docs/');
        $this->assertPayloadContainsPreg('/<li>nr/');

        // update a user
        $this->post('/docs/?user=secret', [
            'username' => 'nr',
            'secret' => 'supersecret2',
        ]);
        $this->assertRedirect('/docs/?user=list');
        $this->get('/docs/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li>admin/');
        $this->assertPayloadContainsPreg('/<li>docs/');
        $this->assertPayloadContainsPreg('/<li>nr/');

        // delete a user
        $this->get('/docs/?user=delete&name=nr');
        $this->assertRedirect('/docs/?user=list');
        $this->get('/docs/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li>admin/');
        $this->assertPayloadContainsPreg('/<li>docs/');
        $this->assertPayloadContainsNotPreg('/<li>nr/');

        // delete again/invalid
        $this->get('/docs/?user=delete&name=nr');
        $this->assertPageError();
        $this->get('/docs/?user=delete&name=');
        $this->assertPageError();
        $this->get('/docs/?user=delete&name=*');
        $this->assertPageError();
        $this->get('/docs/?user=list');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/<li>admin/');
        $this->assertPayloadContainsPreg('/<li>docs/');
        $this->assertPayloadContainsNotPreg('/<li>nr/');
    }
}

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
        $this->assertPageLogin();
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

    public function testDocsCRUD(): void
    {
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        $this->get('/docs/meow');
        $this->assertPageNotFound();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="Create page"/'); // create button

        $this->get('/docs/meow?action=createPage');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/textarea name="content"/'); // editor

        $this->post('/docs/meow?action=save', [
            'title' => 'first title',
            'content' => 'first save',
            'author' => 'first author'
        ]);
        $this->assertRedirect('/docs/meow');
        $this->assertSessionCookie();

        $this->get('/docs/meow');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>first title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>first save<\/p>/');

        $this->get('/docs/meow?action=history');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/id="history-1"/');
        $this->assertPayloadContainsNotPreg('/id="history-2"/');
        $this->assertSessionCookie();

        $this->get('/docs/meow?action=edit');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/value="first title"/');// editor
        $this->assertPayloadContainsPreg('/>first save</');// editor
        $this->assertPayloadContainsPreg('/value="first author"/'); // editor

        $this->post('/docs/meow?action=save', [
            'title' => 'second title',
            'content' => 'second save',
            'author' => 'second author'
        ]);
        $this->assertRedirect('/docs/meow');
        $this->assertSessionCookie();

        $this->get('/docs/meow');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>second title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>second save<\/p>/');

        $this->get('/docs/meow?action=delete');
        $this->assertPage();
        $this->assertSessionCookie();
        $this->assertPayloadContainsPreg('/<h1>second title<\/h1>/');
        $this->assertPayloadContainsPreg('/<p>second save<\/p>/');
        $this->assertPayloadContainsPreg('/value="Delete page"/'); // delete button

        $this->post('/docs/meow?action=deleteOK');
        $this->assertRedirect('/docs/meow');
        $this->assertSessionCookie();

        $this->get('/docs/meow');
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
    }

    public function testEditor(): void
    {
        $this->post('/?auth=login', ['password' => 'doc']);
        $this->assertRedirect('/');
        $this->assertSessionCookie();

        // put first page in edit mode
        $this->get('/docs/install?action=edit');
        $this->assertPage();
        $this->assertPayloadContainsNotPreg('/Someone started editing/');

        // change different page to change alias to xyz
        $this->post('/docs/themes?action=save', [
            'title' => 'destructive save',
            'content' => 'oops',
            'author' => 'xyz'
        ]);
        $this->assertRedirect('/docs/themes');

        sleep(2); // avoid this going so fast that the server won't recognize

        // now we should get the warning
        $this->get('/docs/install?action=edit');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/Someone started editing/');

        // we save anyway
        $this->post('/docs/install?action=save', [
            'title' => 'destructive save 2',
            'content' => 'oops',
            'author' => 'xyz'
        ]);
        $this->assertRedirect('/docs/install');

        // warning gone
        $this->get('/docs/install?action=edit');
        $this->assertPage();
        $this->assertPayloadContainsPreg('/value="destructive save 2"/');
        $this->assertPayloadContainsPreg('/>oops</');
        $this->assertPayloadContainsPreg('/value="xyz"/');
        $this->assertPayloadContainsNotPreg('/Someone started editing/');
    }
}

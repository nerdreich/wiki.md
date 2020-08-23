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

// This file runs integration tests - mainly a CRUD lifecycle roundtrip for a
// wiki page. This assumes that you have the content of `dist/wiki.md` served
// via a local httpd and its URL in $SERVER (default https://wiki.local).
//
// Steps to run it:
// * `gulp dist`
// * launch webserver
// * `php test/it.php`
//
// Warning: This makes destructive calls to wiki pages and erases passwords.
// Do only run it against test instances.

namespace at\nerdreich;

require 'IntegratePHP.php';
$it = new IntegratePHP('https://wiki.local'); // no trailing slash

// --- prepare docroot for tests -----------------------------------------------

// setup pwd file
file_put_contents(
    dirname(__FILE__) . '/../dist/wiki.md/data/.htpasswd',
    'admin:$2y$05$mcqOIM9K4lZfujCONaP7yu32/L5Ptzndf2xRN1/3EMO/UM7qicl8i' . PHP_EOL .
    'docs:$2y$05$KiA/6HVXZ6sQsY9c8.0j/.g6HjBHwrV8lmLvlvxo76EdeIbOzgyBq' . PHP_EOL
);

// --- anonymous user - existing page - all actions ----------------------------

$it->get('/')->assertPage()->assertNoCookies()
    ->assertContains('/Welcome!/');

$it->get('/?action=unknown')
    ->assertPage()
    ->assertNoCookies()
    ->assertContains('/Welcome!/');

$it->get('/?action=createPage')
    ->assertPageError()
    ->assertNoCookies();

$it->get('/?action=edit')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/?action=history')
    ->assertPage()
    ->assertNoCookies()
    ->assertContains('/History for/');

$it->get('/?action=delete')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/?action=deleteOK')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/?action=restore&version=0')
    ->assertPageError()
    ->assertNoCookies();

$it->get('/?action=restore&version=1')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/?auth=login')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/?auth=logout')
    ->assertRedirect('/')
    ->assertNoCookies();

$it->get('/?auth=unknown')
    ->assertPage()
    ->assertNoCookies()
    ->assertContains('/Welcome!/');

// --- anonymous user - non-existing page - all actions ------------------------

$it->get('/meow')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=unknown')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=createPage')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/meow?action=edit')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=history')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=delete')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=deleteOK')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=restore&version=0')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow?action=restore&version=1')
    ->assertPageNotFound()
    ->assertNoCookies();

// --- anonymous user - non-existing folder - all actions ------------------------

$it->get('/meow/')
    ->assertPageNotFound()
    ->assertNoCookies()
    ->assertContainsNot('/value="Create page"/'); // create button

$it->get('/meow/?action=unknown')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow/?action=createPage')
    ->assertPageLogin()
    ->assertNoCookies();

$it->get('/meow/?action=edit')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow/?action=history')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow/?action=delete')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow/?action=deleteOK')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow/?action=restore&version=0')
    ->assertPageNotFound()
    ->assertNoCookies();

$it->get('/meow/?action=restore&version=1')
    ->assertPageNotFound()
    ->assertNoCookies();

// --- login / logout ----------------------------------------------------------

$it->assertNoCookies();

$it->get('/')
    ->assertPage()
    ->assertNoCookies()
    ->assertContains('/<a href="\?auth=login">/')        // login button
    ->assertContainsNot('/<a href="\?action=edit">/')    // edit button
    ->assertContainsNot('/<a href="\?action=history">/') // history button
    ->assertContainsNot('/<a href="\?action=delete">/')  // delete button
    ->assertContainsNot('/<a href="\?action=logout">/'); // logout button

$it->post('/?auth=login', ['password' => 'doc'])
    ->assertRedirect('/')
    ->assertSessionCookie();

$it->get('/')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContainsNot('/<a href="\?auth=login">/')     // login button
    ->assertContainsNot('/<a href="\?action=edit">/')    // edit button
    ->assertContainsNot('/<a href="\?action=history">/') // history button
    ->assertContainsNot('/<a href="\?action=delete">/')  // delete button
    ->assertContains('/<a href="\?auth=logout">/');      // logout button

$it->get('/docs/install')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContainsNot('/<a href="\?auth=login">/')  // login button
    ->assertContains('/<a href="\?action=edit">/')    // edit button
    ->assertContains('/<a href="\?action=history">/') // history button
    ->assertContains('/<a href="\?action=delete">/')  // delete button
    ->assertContains('/<a href="\?auth=logout">/');   // logout button

$it->get('/?auth=logout')
    ->assertRedirect('/')
    ->assertNoCookies();

$it->get('/')
    ->assertPage()
    ->assertNoCookies();

$it->post('/?auth=login', ['password' => 'invalid'])
    ->assertPageLogin()
    ->assertNoCookies();

// --- CRUD docs ---------------------------------------------------------------

$it->post('/?auth=login', ['password' => 'doc'])
    ->assertRedirect('/')
    ->assertSessionCookie();

$it->get('/docs/meow')
    ->assertPageNotFound()
    ->assertSessionCookie()
    ->assertContains('/value="Create page"/'); // create button

$it->get('/docs/meow?action=createPage')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/textarea name="content"/'); // editor

$it->post('/docs/meow?action=save', [
    'title' => 'first title',
    'content' => 'first save',
    'author' => 'first author'
])
    ->assertRedirect('/docs/meow')
    ->assertSessionCookie();

$it->get('/docs/meow')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/<h1>first title<\/h1>/')
    ->assertContains('/<p>first save<\/p>/');

$it->get('/docs/meow?action=history')
    ->assertPage()
    ->assertContains('/id="history-1"/')
    ->assertContainsNot('/id="history-2"/')
    ->assertSessionCookie();

$it->get('/docs/meow?action=edit')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/value="first title"/') // editor
    ->assertContains('/>first save</') // editor
    ->assertContains('/value="first author"/'); // editor

$it->post('/docs/meow?action=save', [
    'title' => 'second title',
    'content' => 'second save',
    'author' => 'second author'
])
    ->assertRedirect('/docs/meow')
    ->assertSessionCookie();

$it->get('/docs/meow')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/<h1>second title<\/h1>/')
    ->assertContains('/<p>second save<\/p>/');

$it->get('/docs/meow?action=history')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/id="history-1"/')
    ->assertContains('/id="history-2"/')
    ->assertContainsNot('/id="history-3"/');

// restore history
$it->post('/docs/meow?action=restore&version=1')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/value="second title"/')
    ->assertContains('/>first save</') // only body gets restored
    ->assertContains('/value="second author"/');

$it->get('/docs/meow?action=delete')
    ->assertPage()
    ->assertSessionCookie()
    ->assertContains('/<h1>second title<\/h1>/')
    ->assertContains('/<p>second save<\/p>/')
    ->assertContains('/value="Delete page"/'); // delete button

$it->post('/docs/meow?action=deleteOK')
    ->assertRedirect('/docs/meow')
    ->assertSessionCookie();

$it->get('/docs/meow')
    ->assertPageNotFound()
    ->assertSessionCookie()
    ->assertContains('/value="Create page"/'); // create button

// -----------------------------------------------------------------------------

$it->success();

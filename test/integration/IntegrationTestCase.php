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

namespace at\nerdreich\wiki;

/**
 * Integration tests for PHP, tailored for wiki.md.
 *
 * Can call and test URLs via CURL. Takes care of PHPSESSID cookie handling.
 */
class IntegrationTestCase extends \PHPUnit\Framework\TestCase
{
    private $server = 'https://wiki.local'; // no trailing slash
    private $url = '';
    private $code = 0;
    private $payload = '';
    private $headers = '';
    private $cookies = [];

    protected function reset(): void
    {
        $this->server = 'https://wiki.local'; // no trailing slash
        $this->url = '';
        $this->code = 0;
        $this->payload = '';
        $this->headers = '';
        $this->cookies = [];
    }

    // --- HTTP calls via CURL -------------------------------------------------

    /**
     * Create a curl resource with common parameters for all HTTP methods.
     *
     * @param string $path Path of url to operate on.
     * @return resource curl resource.
     */
    private function prepareRequest(string $path)
    {
        $this->url = $this->server . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        if (array_key_exists('PHPSESSID', $this->cookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: PHPSESSID=' . $this->cookies['PHPSESSID']));
        }
        return $ch;
    }

    /**
     * Execute curl request and parse reply from server.
     *
     * Will handle payload, headers and cookies.
     *
     * @param resource $ch curl resource.
     */
    private function parseRequest($ch): void
    {
        $this->payload = curl_exec($ch);
        $this->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->headers = substr($this->payload, 0, strpos($this->payload, "\r\n\r\n"));
        $this->payload = substr($this->payload, strpos($this->payload, "\r\n\r\n") + 4); // strip $this->headers

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $this->headers, $matches);
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            foreach ($cookie as $key => $value) {
                if ($value === 'deleted') {
                    unset($this->cookies[$key]);
                } else {
                    $this->cookies[$key] = $value;
                }
            }
        }

        curl_close($ch);
    }

    /**
     * Fetch an URL via HTTP GET.
     *
     * @param string $path Path to GET.
     */
    public function get(
        string $path
    ): IntegrationTestCase {
        $ch = $this->prepareRequest($path);
        $this->parseRequest($ch);

        return $this;
    }

    /**
     * Fetch an URL via HTTP POST.
     *
     * @param string $url Path to POST.
     * @param array $fields Data to send to server.
     */
    public function post(
        string $path,
        array $fields = []
    ): IntegrationTestCase {
        $ch = $this->prepareRequest($path);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $this->parseRequest($ch);

        return $this;
    }

    /**
     * Fetch an URL via HTTP DELETE.
     *
     * @param string $url Path to DELETE.
     */
    public function delete(
        string $path
    ): IntegrationTestCase {
        $ch = $this->prepareRequest($path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $this->parseRequest($ch);

        return $this;
    }

    // --- asserts & checks ----------------------------------------------------

    /**
     * Assert that last HTTP request returned a HTML page.
     *
     * Will abort if PHP errors are found or it seems not to be complete
     * (missing end tag).
     *
     * @param int $statusCode Expected HTTP status code.
     */
    public function assertPage(int $statusCode = 200): void
    {
        $this->assertEquals($statusCode, $this->code);
        $this->assertStringNotContainsString('Stack trace', $this->payload);
        $this->assertMatchesRegularExpression('/<\/html>\s+$/', $this->payload);
    }

    /**
     * Assert that last HTTP request returned a 302 redirect.
     *
     * @param string $path Expected location for redirect.
     */
    public function assertRedirect(string $path): void
    {
        $this->assertEquals(302, $this->code);
        $this->assertStringNotContainsString('Stack trace', $this->payload);
        $this->assertMatchesRegularExpression('/^\s*$/', $this->payload);
        $this->assertMatchesRegularExpression(
            '/^location: ' . str_replace('?', '\?', str_replace('/', '\/', $path)) . '\s+$/m',
            $this->headers
        );
    }

    /**
     * Assert that last HTTP request returned the wiki.md error page.
     */
    public function assertPageError(): void
    {
        $this->assertPage(401);
        $this->assertPayloadContainsPreg('/an error occured/');
    }

    /**
     * Assert that last HTTP request returned the wiki.md login page.
     */
    public function assertPageLogin(): void
    {
        $this->assertPage(401);
        $this->assertPayloadContainsPreg('/Login required/');
    }

    /**
     * Assert that last HTTP request returned the wiki.md permission-denied
     * page for logged-in users.
     */
    public function assertPageDenied(): void
    {
        $this->assertPage(403);
        $this->assertPayloadContainsPreg('/You do not have the necessary permissions/');
    }

    /**
     * Assert that last HTTP request returned the wiki.md 404 page.
     */
    public function assertPageNotFound(): void
    {
        $this->assertPage(404);
        $this->assertPayloadContainsPreg('/does not exist/');
    }

    /**
     * Assert that last HTTP request's body contained a regular expression.
     *
     * @param $preg Regular expression (including /'s).
     */
    public function assertPayloadContainsPreg(string $preg): void
    {
        $this->assertMatchesRegularExpression($preg, $this->payload);
    }

    /**
     * Assert that last HTTP request's body did not contain a regular expression.
     *
     * @param $preg Regular expression (including /'s).
     */
    public function assertPayloadContainsNotPreg(string $preg): void
    {
        $this->assertDoesNotMatchRegularExpression($preg, $this->payload);
    }

    /**
     * Assert that our internal HTTP session does not contain any cookies (yet).
     */
    public function assertNoCookies(): void
    {
        if (array_key_exists('PHPSESSID', $this->cookies) && $this->cookies['PHPSESSID'] === 'deleted') {
            $this->assertCount(1, $this->cookies);
        } else {
            $this->assertCount(0, $this->cookies);
        }
    }

    /**
     * Assert that our internal HTTP session does contain the PHP session cookie
     * (and only that cookie).
     *
     * @return string The current session token.
     */
    public function assertSessionCookie(?string $token = null): string
    {
        $this->assertCount(1, $this->cookies);
        $this->assertNotEmpty($this->cookies['PHPSESSID']);
        if ($token !== null) {
            $this->assertEquals($this->cookies['PHPSESSID'], $token);
        }
        return $this->cookies['PHPSESSID'];
    }
}

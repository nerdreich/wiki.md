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

namespace at\nerdreich;

/**
 * Integration tests for PHP, tailored for wiki.md.
 *
 * Can call and test URLs via CURL. Takes care of PHPSESSID cookie handling.
 */
class IntegratePHP
{
    private $server = '';
    private $url = '';
    private $code = 0;
    private $payload = '';
    private $headers = '';
    private $successes = 0;
    private $cookies = [];

    /**
     * Constructor
     *
     * @param array $server URL of server to test. No trailing slash.
     */
    public function __construct(
        string $server
    ) {
        $this->server = $server;
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
    ): IntegratePHP {
        echo 'GET : ' . $path . PHP_EOL;
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
    ): IntegratePHP {
        echo 'POST: ' . $path . PHP_EOL;
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
    ): IntegratePHP {
        echo 'DEL : ' . $path . PHP_EOL;
        $ch = $this->prepareRequest($path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $this->parseRequest($ch);

        return $this;
    }

    // --- helpers -------------------------------------------------------------

    /**
     * Abort testing and print error message.
     *
     * Will terminate execution of the calling script.
     *
     * @param string $message Message to print.
     */
    private function abort(string $message)
    {
        echo '----------------------------------------------------------' . PHP_EOL;
        echo 'ERR : ' . $message . PHP_EOL;
        echo 'URL : ' . $this->url . PHP_EOL;
        echo 'HEAD: ' . PHP_EOL . $this->headers . PHP_EOL;
        echo 'BODY: ' . PHP_EOL . $this->payload . PHP_EOL;
        exit;
    }

    /**
     * Print success message after a test run.
     *
     * Will terminate execution of the calling script.
     */
    public function success(): void
    {
        echo 'Success! ' . $this->successes . ' tests positive.' . PHP_EOL;
        exit;
    }

    // --- asserts & checks ----------------------------------------------------

    /**
     * Assert that last HTTP request returned a HTML page.
     *
     * Will abort if PHP errors are found or it seems not to be complete
     * (missing end tag).
     *
     * @param int $statusCode Expected HTTP status code.
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertPage(int $statusCode = 200): IntegratePHP
    {
        $this->code == $statusCode || $this->abort($this->code . ' is not a ' . $statusCode . '.');
        preg_match('/Stack trace/', $this->payload) && $this->abort('PHP error found.');
        preg_match('/<\/html>\s+$/', $this->payload) || $this->abort('Not a complete page.');
        $this->successes++;

        return $this;
    }

    /**
     * Assert that last HTTP request returned a 302 redirect.
     *
     * @param string $path Expected location for redirect.
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertRedirect(string $path): IntegratePHP
    {
        $this->code == 302 || $this->abort($this->code . ' is not a 302.');
        preg_match('/Stack trace/', $this->payload) && $this->abort('PHP error found.');
        preg_match('/^\s*$/', $this->payload) || $this->abort('Not an empty page.');
        preg_match(
            '/^location: ' . str_replace('/', '\/', $path) . '\s+$/m',
            $this->headers
        ) || $this->abort('Location is not ' . $path . '.');
        $this->successes++;

        return $this;
    }

    /**
     * Assert that last HTTP request returned the wiki.md error page.
     *
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertPageError(): IntegratePHP
    {
        $this->assertPage(400);
        $this->assertContains('/an error occured/');

        return $this;
    }

    /**
     * Assert that last HTTP request returned the wiki.md login page.
     *
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertPageLogin(): IntegratePHP
    {
        $this->assertPage(401);
        $this->assertContains('/Password required/');

        return $this;
    }

    /**
     * Assert that last HTTP request returned the wiki.md permission-denied
     * page for logged-in users.
     *
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertPageDenied(): IntegratePHP
    {
        $this->assertPage(403);
        $this->assertContains('/You do not have the necessary permissions/');

        return $this;
    }

    /**
     * Assert that last HTTP request returned the wiki.md 404 page.
     *
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertPageNotFound(): IntegratePHP
    {
        $this->assertPage(404);
        $this->assertContains('/does not exist/');

        return $this;
    }

    /**
     * Assert that last HTTP request's body contained a regular expression.
     *
     * @param $preg Regular expression (including /'s).
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertContains(string $preg): IntegratePHP
    {
        preg_match($preg, $this->payload) || $this->abort('Regular expression ' . $preg . ' not found.');
        $this->successes++;

        return $this;
    }

    /**
     * Assert that last HTTP request's body did not contain a regular expression.
     *
     * @param $preg Regular expression (including /'s).
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertContainsNot(string $preg): IntegratePHP
    {
        preg_match($preg, $this->payload) && $this->abort('Regular expression ' . $preg . ' found.');
        $this->successes++;

        return $this;
    }

    /**
     * Assert that our internal HTTP session does not contain any cookies (yet).
     *
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertNoCookies(): IntegratePHP
    {
        if (array_key_exists('PHPSESSID', $this->cookies) && $this->cookies['PHPSESSID'] === 'deleted') {
            count($this->cookies) === 1 || $this->abort('Expected no cookies, but found some.');
        } else {
            count($this->cookies) === 0 || $this->abort('Expected no cookies, but found some.');
        }
        $this->successes++;

        return $this;
    }

    /**
     * Assert that our internal HTTP session does contain the PHP session cookie
     * (and only that cookie).
     *
     * @return IntegratePHP Current instance of this class for chaining.
     */
    public function assertSessionCookie(): IntegratePHP
    {
        count($this->cookies) === 1 || $this->abort('Expected one cookie, but ' . count($this->cookies) . ' found.');
        strlen($this->cookies['PHPSESSID']) > 16 || $this->abort('No PHPSESSID cookie found.');
        $this->successes++;

        return $this;
    }
}

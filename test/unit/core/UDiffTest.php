<?php

/**
 * Copyright 2020-2022 Markus Leupold-Löwenthal
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

require_once('dist/wiki.md/core/UDiff.php');

final class UDiffTest extends \PHPUnit\Framework\TestCase
{
    public function testDiff1(): void
    {
        $before = "I am the document\n"
          . "and will change.\n"
          . "\n"
          . "this line will change.\n"
          . "\n"
          . "and one more line\n"
          . "\n";
        $after = "I am the document\n"
            . "and will change.\n"
            . "\n"
            . "this line has changed.\n"
            . "\n"
            . "and one more line\n"
            . "\n";
        $diff = "--- old\n"
            . "+++ new\n"
            . "@@ -4,1 +4,1 @@\n"
            . "-this line will change.\n"
            . "+this line has changed.\n";
        $this->assertEquals($diff, UDiff::diff($before, $after));
    }

    public function testDiff2(): void
    {
        $before = "aaa\n"
            . "bbb\n"
            . "ccc\n"
            . "ddd\n"
            . "eee\n"
            . "fff\n"
            . "ggg\n"
            . "hhh\n";
        $after = "AAA\n"
            . "bbb\n"
            . "ccc\n"
            . "DDD\n"
            . "EEE\n"
            . "fff\n"
            . "ggg\n"
            . "HHH\n";
        $diff = "--- file1.md\n"
            . "+++ file2.txt\n"
            . "@@ -1,1 +1,1 @@\n"
            . "-aaa\n"
            . "+AAA\n"
            . "@@ -4,2 +4,2 @@\n"
            . "-ddd\n"
            . "-eee\n"
            . "+DDD\n"
            . "+EEE\n"
            . "@@ -8,1 +8,1 @@\n"
            . "-hhh\n"
            . "+HHH\n";
        $this->assertEquals($diff, UDiff::diff($before, $after, 'file1.md', 'file2.txt'));
    }

    public function testDiff3(): void
    {
        $before = "first\n"
            . "\n"
            . "second\n"
            . "\n"
            . "third\n";
        $after = "first - update\n"
            . "\n"
            . "second\n"
            . "\n"
            . "third\n";
        $diff = "--- file1.md\n"
            . "+++ file2.txt\n"
            . "@@ -1,1 +1,1 @@\n"
            . "-first\n"
            . "+first - update\n";
        $this->assertEquals($diff, UDiff::diff($before, $after, 'file1.md', 'file2.txt'));
    }

    public function testDiff4(): void
    {
        $before = "first\n"
            . "\n"
            . "second\n";
        $after = "first - update\n"
            . "\n"
            . "second\n"
            . "\n"
            . "third\n";
        $diff = "--- file1.md\n"
            . "+++ file2.txt\n"
            . "@@ -1,1 +1,1 @@\n"
            . "-first\n"
            . "+first - update\n"
            . "@@ -4,0 +4,2 @@\n"
            . "+\n"
            . "+third\n";
        $this->assertEquals($diff, UDiff::diff($before, $after, 'file1.md', 'file2.txt'));
    }

    public function testPatchWithFilenames(): void
    {
        $before = "aaa\nbbb\nccc\n";
        $after = "AAA\nbbb\nCCC\n";

        $diffForward = UDiff::diff($before, $after);
        $diffBackward = UDiff::diff($after, $before);

        $this->assertTrue($diffForward !== $diffBackward);

        $this->assertEquals($after, UDiff::patch($before, $diffForward));
        $this->assertEquals($before, UDiff::patch($after, $diffBackward));

        $this->assertEquals($before, UDiff::patch($after, $diffForward, true));
        $this->assertEquals($after, UDiff::patch($before, $diffBackward, true));
    }

    public function testPatchWithoutFilenames(): void
    {
        // in this test we remove the optional +++/--- lines at the beginning of a diff

        $before = "aaa\nbbb\nccc\n";
        $after = "AAA\nbbb\nCCC\n";

        $diffForward = UDiff::diff($before, $after);
        $diffForward = preg_replace('/^.+\n/', '', $diffForward); // remove first line (---)
        $diffForward = preg_replace('/^.+\n/', '', $diffForward); // remove second line (+++)

        $diffBackward = UDiff::diff($after, $before);
        $diffBackward = preg_replace('/^.+\n/', '', $diffBackward); // remove first line (---)
        $diffBackward = preg_replace('/^.+\n/', '', $diffBackward); // remove second line (+++)

        $this->assertTrue($diffForward !== $diffBackward);

        $this->assertEquals($after, UDiff::patch($before, $diffForward));
        $this->assertEquals($before, UDiff::patch($after, $diffBackward));

        $this->assertEquals($before, UDiff::patch($after, $diffForward, true));
        $this->assertEquals($after, UDiff::patch($before, $diffBackward, true));
    }
}

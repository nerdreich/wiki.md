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
 * A simple PHP unified diff implementation.
 *
 * Will detect changes between two strings/arrays/files and output them
 * in unified diff format.
 *
 * Will sacrifice speed for better readability of code.
 */
class UDiff
{
    /**
     * Find differences between two texts/strings.
     *
     * @param string $a The first / left text.
     * @param string $b The second / right text.
     * @param string $filenameA Optional filename to use in diff output.
     * @param string $filenameB Optional filename to use in diff output.
     * @return string Diff in unified diff (udiff) format, or null if there are
     *                no changes.
     */
    public static function diff(
        string $a,
        string $b,
        string $filenameA = 'old',
        string $filenameB = 'new'
    ): ?string {
        $diff = '';

        $arrayA = explode("\n", str_replace("\r", '', $a));
        $arrayB = explode("\n", str_replace("\r", '', $b));

        // add dummy zero-index items so we can work with one-based
        // index numbers from now on
        array_unshift($arrayA, 'diff');
        array_unshift($arrayB, 'diff');

        $diff .= UDiff::findNextHunk(
            $arrayA,
            1,
            count($arrayA) - 1,
            $arrayB,
            1,
            count($arrayB) - 1,
        );

        if ($diff !== '') {
            return "--- $filenameA\n+++ $filenameB\n" . $diff;
        }

        return null;
    }

    /**
     * Apply a diff (patch) content.
     *
     * Note: this implementation assumes a valid diff without invalid indices.
     *
     * @param string $in The content to patch.
     * @param string $diff A unified diff to apply.
     * @param bool $reverse If false (default), apply patch forward.
     *                Reverse backwards otherwise.
     * @return string Patched Data.
     */
    public function patch(
        string $in,
        string $diff,
        bool $reverse = false
    ): string {
        $arrayIn = explode("\n", str_replace("\r", '', $in));
        $arrayOut = [];
        $lineIn = 1;

        // skip optional +++/--- lines at the beginning of diff
        $diff = preg_replace('/^[+-][+-][+-].*\n/', '', $diff);
        $diff = preg_replace('/^[+-][+-][+-].*\n/', '', $diff);

        // find and apply chunks (starting with @@)
        foreach (explode("\n", str_replace("\r", '', $diff)) as $diffLine) {
            if (preg_match('/^@@/', $diffLine)) {
                list($fromA, $lengthA, $fromB, $lengthB) = UDiff::parsePatch($diffLine);

                if (!$reverse) {
                    while ($lineIn < $fromA) { // copy unaffected lines
                        $arrayOut[] = $arrayIn[$lineIn - 1];
                        $lineIn++;
                    }
                    $lineIn += $lengthA; // skip replaced lines from A
                } else {
                    while ($lineIn < $fromB) { // copy unaffected lines
                        $arrayOut[] = $arrayIn[$lineIn - 1];
                        $lineIn++;
                    }
                    $lineIn += $lengthB; // skip replaced lines from A
                }
            } elseif (!$reverse && preg_match('/^\+/', $diffLine)) {
                $arrayOut[] = substr($diffLine, 1);
            } elseif ($reverse && preg_match('/^\-/', $diffLine)) {
                $arrayOut[] = substr($diffLine, 1);
            }
        }

        // copy remainin unchanged lines
        for ($i = $lineIn; $i <= count($arrayIn); $i++) {
            $arrayOut[] = $arrayIn[$i - 1];
        }

        return implode("\n", $arrayOut);
    }

    /**
     * Parse first line of a diff's patch to see how many lines in file A or B are affected.
     *
     * @return array Array containing the 4 values of a patch line.
     */
    private function parsePatch(
        string $diffLine
    ): array {
        preg_match('/@@ -([0-9]+),([0-9]+) \+([0-9]+),([0-9]+) @@/', $diffLine, $matches);
        return array((int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4]);
    }

    /**
     * Detect hunk of change between two arrays-of-strings (lines).
     *
     * @param string $a The first/left content, as array of strings/lines.
     * @param int $aStart Line in $a where to start comparing with.
     * @param int $aEnd Line in $a where to end comparing with.
     * @param string $b The first/left content, as array of strings/lines.
     * @param int $bStart Line in $a where to start comparing with.
     * @param int $bEnd Line in $a where to end comparing with.
     * @param int $depth Safety counter to avoid endless recursions.
     * @return string Next best hunk for the diff.
     */
    private static function findNextHunk(
        array $a,
        int $aStart,
        int $aEnd,
        array $b,
        int $bStart,
        int $bEnd,
        int $depth = 0
    ): string {
        if ($depth > 16 || $aStart > $aEnd || $bStart > $bEnd) {
            return '';
        }

        // skip equal lines at start of content
        while ($aStart <= $aEnd) {
            if ($a[$aStart] === $b[$bStart]) {
                $aStart++;
                $bStart++;
            } else {
                break;
            }
        }

        // skip equal lines at end of content
        while ($aEnd >= $aStart && $bEnd >= $bStart) {
            if ($a[$aEnd] === $b[$bEnd]) {
                $aEnd--;
                $bEnd--;
            } else {
                break;
            }
        }

        // find smallest a->b and b->a match
        $equalAgainAB = UDiff::findClosestEqualLine(
            $a,
            $aStart,
            $aEnd,
            $b,
            $bStart,
            $bEnd
        );
        $equalAgainBA = UDiff::findClosestEqualLine(
            $b,
            $bStart,
            $bEnd,
            $a,
            $aStart,
            $aEnd
        );

        return UDiff::extractHunk(
            $a,
            $aStart,
            $equalAgainAB - 1,
            $b,
            $bStart,
            $equalAgainBA - 1,
            $depth + 1
        ) . UDiff::findNextHunk(
            $a,
            $equalAgainAB + 1,
            $aEnd,
            $b,
            $equalAgainBA + 1,
            $bEnd,
            $depth + 1
        );
    }

    /**
     * Guess when a changed block ends.
     *
     * @param string $a The first/left content, as array of strings/lines.
     * @param int $aStart Line in $a where to start comparing with.
     * @param int $aEnd Line in $a where to end comparing with.
     * @param string $b The first/left content, as array of strings/lines.
     * @param int $bStart Line in $a where to start comparing with.
     * @param int $bEnd Line in $a where to end comparing with.
     * @return int Line where the chunk ends.
     */
    private static function findClosestEqualLine(
        array $a,
        int $aStart,
        int $aEnd,
        array $b,
        int $bStart,
        int $bEnd
    ): int {
        $delta = max($aEnd, $bEnd) + 1;
        $index = $aEnd + 1;
        for ($i = $aStart; $i < $aEnd; $i++) {
            for ($j = $bStart; $j < $bEnd; $j++) {
                if ($a[$i] === $b[$j]) {
                    if (abs($i - $j) < $delta) {
                        $delta = abs($i - $j);
                        $index = $i;
                    }
                }
            }
        }
        return $index;
    }

    /**
     * Generate one hunk snippet.
     *
     * @param string $a The first/left content, as array of strings/lines.
     * @param int $aStart Line in $a where to start comparing with.
     * @param int $aEnd Line in $a where to end comparing with.
     * @param string $b The first/left content, as array of strings/lines.
     * @param int $bStart Line in $a where to start comparing with.
     * @param int $bEnd Line in $a where to end comparing with.
     * @return string Hunk text for diff file.
     */
    private static function extractHunk(
        array $a,
        int $aStart,
        int $aEnd,
        array $b,
        int $bStart,
        int $bEnd
    ): string {
        // what remains is a cheap hunk
        $aHunkSize = $aEnd - $aStart + 1;
        $bHunkSize = $bEnd - $bStart + 1;

        if ($aHunkSize <= 0 && $bHunkSize <= 0) {
            return ''; // equal content
        }

        // output hunk
        $hunk = "@@ -$aStart,$aHunkSize +$bStart,$bHunkSize @@\n";

        for ($i = $aStart; $i <= $aEnd; $i++) {
            $hunk .= '-' . $a[$i] . "\n";
        }
        for ($i = $bStart; $i <= $bEnd; $i++) {
            $hunk .= '+' . $b[$i] . "\n";
        }

        return $hunk;
    }
}

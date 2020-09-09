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

if (!class_exists('\at\nerdreich\wiki\MediaPlugin')) {

    /**
     * Media manager plugin for wiki.md.
     *
     * Adds management UIs to list, upload and delete media files (e.g. pictures).
     */
    class MediaPlugin extends WikiPlugin
    {
        private $pregMedia = '/\.(gif|jpg|png)$/i'; // files matching this are considered media
        private $mediaTypes = 'gif|jpg|png';        // shown to humans

        public function setup()
        {
            $this->wiki->registerPageRoute(function ($wiki) {
                $wikiPath = $wiki->core->getWikiPath();
                if ($this->isMedia() && $wiki->core->mayReadPath($wikiPath)) {
                    $this->renderMedia($this->getMediaFileFS($wikiPath));
                }
            });

            $this->wiki->registerActionRoute('media', 'list', function ($wiki) {
                if ($this->list() !== null) {
                    $wiki->renderPluginFile('media', 'list');
                }
                $wiki->renderLoginOrDenied(); // transparent login
            });

            $this->wiki->registerActionRoute('media', 'upload', function ($wiki) {
                if ($_FILES['wikimedia'] && $_FILES['wikimedia']['error'] === UPLOAD_ERR_OK) {
                    if (
                        $this->upload(
                            $_FILES['wikimedia']['tmp_name'],
                            strtolower(trim($_FILES['wikimedia']['name'])),
                            $wiki->core->getWikiPath()
                        )
                    ) {
                        $wiki->redirect($wiki->core->getLocation(), 'media=list');
                    }
                } else { // upload failed, probably due empty selector on submit
                    $wiki->redirect($wiki->core->getLocation(), 'media=list');
                }
            });

            $this->wiki->registerActionRoute('media', 'delete', function ($wiki) {
                if ($this->delete() !== null) {
                    $wiki->redirect(dirname($wiki->core->getLocation()) . '/', 'media=list');
                }
            });

            if ($this->mayMedia()) {
                $this->wiki->addMenuItem('media=list', 'Media');
            }
        }

        /**
         * Deliver a file, usually an image or uploaded file, to the client.
         *
         * Will set proper HTTP headers and terminate execution after sending the blob.
         *
         * @param string $pathFS Absolute path of file to send.
         */
        public static function renderMedia(string $pathFS): void
        {
            header('Content-Type:' . mime_content_type($pathFS));
            header('Content-Length: ' . filesize($pathFS));
            readfile($pathFS);
            exit;
        }

        /**
         * Check if the current user may administrate media & uploads.
         *
         * @param string $wikiPath The path to check the permission for.
         * @return boolean True, if permissions are sufficient. False otherwise.
         */
        public function mayMedia(
            ?string $wikiPath = null
        ): bool {
            $wikiPath = $wikiPath ?? $this->core->getWikiPath();
            return $this->user->hasPermission('mediaAdmin', $wikiPath);
        }

        /**
         * Check if the current wiki page is a media object (image, ...).
         *
         * @return boolean True, if so. False if not.
         */
        public function isMedia(): bool
        {
            $wikiPath = $this->core->getWikiPath();
            if (preg_match($this->pregMedia, $wikiPath)) {
                if (is_file($this->getMediaFileFS($wikiPath))) {
                    return true;
                };
            }
            return false;
        }

        /**
         * Determine the media file for a wiki path.
         *
         * @param string $wikiPath Wiki Path to a media file (e.g. /animal/lion.jpg).
         * @return string Absolute directory of media folder on disk.
         */
        private function getMediaFileFS(
            string $wikiPath
        ): string {
            return $this->getMediaDirFS($wikiPath) . '/' . basename($wikiPath);
        }

        /**
         * Determine the media directory for a wiki path.
         *
         * @param string $wikiPath Wiki Path.
         * @return string Absolute directory of media folder on disk.
         */
        private function getMediaDirFS(
            string $wikiPath
        ): string {
            if ($wikiPath[-1] === '/') {
                return $this->core->getContentDirFS() . $wikiPath . '_media/';
            } else {
                return $this->core->getContentDirFS() . dirname($wikiPath) . '/_media/';
            }
        }

        /**
         * Get the media types allowed for upload.
         *
         * @return string Both human-readable and HTML5 pattern string for UI, e.g. 'gif|jpg|png'.
         */
        public function getMediaTypes(): string
        {
            return $this->mediaTypes;
        }

        /**
         * Get the media size limit.
         *
         * Might be limited by wiki.md's config or by php.ini.
         *
         * @return int Limit in bytes.
         */
        public function getMediaSizeLimit(): string
        {
            return min(
                (int)($this->config['media_size_limit_kb'] ?? 4096),
                ((int)(ini_get('upload_max_filesize'))) * 1024,
                ((int)(ini_get('post_max_size'))) * 1024,
                ((int)(ini_get('memory_limit'))) * 1024
            );
        }

        /**
         * Provide all data for the media/upload page.
         *
         * @param string $wikiPath WikiPath of folder to administer.
         * @param array Array containing 'media'.
         */
        public function list(
            string $wikiPath = null
        ): ?array {
            $mediaFolder = $this->core->getWikiPathParentFolder($wikiPath ?? $this->core->getWikiPath());
            if ($this->mayMedia($mediaFolder)) {
                $files = [];
                $mediaDirFS = $this->getMediaDirFS($mediaFolder);
                if (is_dir($mediaDirFS)) {
                    foreach (new \DirectoryIterator($mediaDirFS) as $fileinfo) {
                        if (!$fileinfo->isDot() && preg_match($this->pregMedia, $fileinfo->getFilename())) {
                            $file = [];
                            $file['name'] = $fileinfo->getFilename();
                            $file['path'] = $fileinfo->getFilename();
                            $file['size'] = $fileinfo->getSize();
                            $file['mtime'] = $fileinfo->getMtime();
                            $files[] = $file;
                        }
                    }
                }
                return $files;
            }
            return null;
        }

        /**
         * Delete a media file.
         *
         * @param string $wikiPath WikiPath of file to delete.
         * @return bool True if file could be deleted.
         */
        public function delete(
            ?string $wikiPath = null
        ): bool {
            $wikiPath = $this->core->getWikiPath();
            if ($this->mayMedia($wikiPath)) {
                $file = $this->getMediaDirFS($wikiPath) . basename($wikiPath);
                if (is_file($file)) {
                    unlink($file);
                    return true;
                }
            }
            return false;
        }

        /**
         * Add an uploaded file to the content directories.
         *
         * @param string $tempName Temporary file/path where PHP uploaded the file.
         * @param string $filename File name (from the client). E.g. 'mypic.jpg'.
         * @param string $wikiPath WikiPath of file to delete.
         * @return bool True if file could be deleted.
         */
        public function upload(
            $tempName,
            $filename,
            $wikiPath
        ): bool {
            if (preg_match($this->pregMedia, $filename)) { // image etc.
                if ($this->mayMedia($wikiPath)) {
                    $targetDir = $this->getMediaDirFS($wikiPath);
                    if (!\file_exists($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    $target = $targetDir . '/' . $filename;
                    if (move_uploaded_file($tempName, $target)) {
                        return true;
                    }
                }
            }
            return false;
        }

        /**
         * Convert bytes into kb/MB/GB strings.
         *
         * @param int $bytes A value, e.g. 3826.
         * @return string Human readable version, e.g. '3.8 kB'.
         */
        public static function mediaSize(
            int $bytes
        ): string {
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $bytes /= pow(1024, $pow);
            return round($bytes, 1) . ' ' . ['B', 'kB', 'MB', 'GB', 'TB'][$pow];
        }
    }

    $GLOBALS['wiki.md-plugins']['media'] = '\at\nerdreich\wiki\MediaPlugin';
}

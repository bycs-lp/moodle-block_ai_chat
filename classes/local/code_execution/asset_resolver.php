<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_ai_chat\local\code_execution;

/**
 * Resolves requested files of the bundled code execution libraries (Pyodide, RDKit.js).
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asset_resolver {
    /** @var string Revision of the bundled libraries, bump on every library update to invalidate browser caches. */
    public const REVISION = 'pyodide-314.0.7-rdkit-2026.9.1';

    /** @var string[] Library directories below thirdparty/ that may be served. */
    public const LIBRARIES = ['pyodide', 'rdkit'];

    /** @var array<string, string> Allowed file extensions and their mime types. */
    public const MIMETYPES = [
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'wasm' => 'application/wasm',
        'json' => 'application/json',
        'zip' => 'application/zip',
        'whl' => 'application/zip',
    ];

    /** @var string Absolute path of the thirdparty directory */
    private string $basedir;

    /**
     * Constructor.
     *
     * @param string|null $basedir directory containing the library directories, defaults to the plugin's thirdparty dir
     */
    public function __construct(?string $basedir = null) {
        $this->basedir = $basedir ?? dirname(__DIR__, 3) . '/thirdparty';
    }

    /**
     * Resolves a requested path of the form "<library>/<file>" to an absolute file path.
     *
     * @param string $relativepath the requested path
     * @return string|null absolute path of the file or null if the path must not be served
     */
    public function resolve(string $relativepath): ?string {
        if (!preg_match('#^([a-z]+)/([A-Za-z0-9_+-][A-Za-z0-9_.+-]*)$#', $relativepath, $matches)) {
            return null;
        }
        [, $library, $filename] = $matches;
        if (!in_array($library, self::LIBRARIES, true) || self::get_mimetype($filename) === null) {
            return null;
        }
        $librarydir = realpath($this->basedir . '/' . $library);
        $path = realpath($this->basedir . '/' . $library . '/' . $filename);
        if ($librarydir === false || $path === false || !is_file($path) || !str_starts_with($path, $librarydir . '/')) {
            return null;
        }
        return $path;
    }

    /**
     * Returns the mime type for a file name or null if the extension is not allowed.
     *
     * @param string $filename the file name
     * @return string|null the mime type
     */
    public static function get_mimetype(string $filename): ?string {
        return self::MIMETYPES[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * Returns the base URL under which the files of a library are served.
     *
     * @param string $wwwroot the site's wwwroot
     * @param string $library one of self::LIBRARIES
     * @return string URL ending with a slash
     */
    public static function get_library_url(string $wwwroot, string $library): string {
        return $wwwroot . '/blocks/ai_chat/codeexecution/asset.php/' . self::REVISION . '/' . $library . '/';
    }
}

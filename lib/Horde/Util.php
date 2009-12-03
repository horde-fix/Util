<?php
/**
 * The Horde_Util:: class provides generally useful methods.
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @package Horde_Util
 */
class Horde_Util
{
    /* Error code for a missing driver configuration. */
    const HORDE_ERROR_DRIVER_CONFIG_MISSING = 1;

    /* Error code for an incomplete driver configuration. */
    const HORDE_ERROR_DRIVER_CONFIG = 2;

    /**
     * A list of random patterns to use for overwriting purposes.
     * See http://www.cs.auckland.ac.nz/~pgut001/pubs/secure_del.html.
     * We save the random overwrites for efficiency reasons.
     *
     * @var array
     */
    static public $patterns = array(
        "\x55", "\xaa", "\x92\x49\x24", "\x49\x24\x92", "\x24\x92\x49",
        "\x00", "\x11", "\x22", "\x33", "\x44", "\x55", "\x66", "\x77",
        "\x88", "\x99", "\xaa", "\xbb", "\xcc", "\xdd", "\xee", "\xff",
        "\x92\x49\x24", "\x49\x24\x92", "\x24\x92\x49", "\x6d\xb6\xdb",
        "\xb6\xdb\x6d", "\xdb\x6d\xb6"
    );

    /**
     * TODO
     *
     * @var array
     */
    static public $dateSymbols = array(
        'a', 'A', 'd', 'D', 'F', 'g', 'G', 'h', 'H', 'i', 'j', 'l', 'm', 'M',
        'n', 'r', 's', 'T', 'w', 'W', 'y', 'Y', 'z', 'm/d/Y', 'M', "\n",
        'g:i a', 'G:i', "\t", 'H:i:s', '%'
    );

    /**
     * TODO
     *
     * @var array
     */
    static public $strftimeSymbols = array(
        '%p', '%p', '%d', '%a', '%B', '%I', '%H', '%I', '%H', '%M', '%e',
        '%A', '%m', '%b', '%m', '%a, %e %b %Y %T %Z', '%S', '%Z', '%w', '%V',
        '%y', '%Y', '%j', '%D', '%h', '%n', '%r', '%R', '%t', '%T', '%%'
    );

    /**
     * Temp directory locations.
     *
     * @var array
     */
    static public $tmpLocations = array(
        '/tmp', '/var/tmp', 'c:\WUTemp', 'c:\temp', 'c:\windows\temp',
        'c:\winnt\temp'
    );

    /**
     * Random number for nocacheUrl().
     *
     * @var integer.
     */
    static protected $_randnum = null;

    /**
     * TODO
     */
    static protected $_magicquotes = null;

    /**
     * TODO
     */
    static protected $_shutdowndata = array(
        'dirs' => array(),
        'files' => array(),
        'secure' => array()
    );

    /**
     * TODO
     */
    static protected $_shutdownreg = false;

    /**
     * Cache for extensionExists().
     *
     * @var array
     */
    static protected $_cache = array();

    /**
     * Returns an object's clone.
     *
     * @param object &$obj  The object to clone.
     *
     * @return object  The cloned object.
     */
    static public function &cloneObject(&$obj)
    {
        if (!is_object($obj)) {
            $bt = debug_backtrace();
            if (isset($bt[1])) {
                $caller = $bt[1]['function'];
                if (isset($bt[1]['class'])) {
                    $caller = $bt[1]['class'].$bt[1]['type'].$caller;
                }
            } else {
                $caller = 'main';
            }

            $caller .= ' on line ' . $bt[0]['line'] . ' of ' . $bt[0]['file'];
            Horde::logMessage('Horde_Util::cloneObject called on variable of type ' . gettype($obj) . ' by ' . $caller, __FILE__, __LINE__, PEAR_LOG_DEBUG);

            $ret = $obj;
            return $ret;
        }

        $ret = clone($obj);
        return $ret;
    }

    /**
     * Buffers the output from a function call, like readfile() or
     * highlight_string(), that prints the output directly, so that instead it
     * can be returned as a string and used.
     *
     * @param string $function  The function to run.
     * @param mixed $arg1       First argument to $function().
     * @param mixed $arg2       Second argument to $function().
     * @param mixed $arg...     ...
     * @param mixed $argN       Nth argument to $function().
     *
     * @return string  The output of the function.
     */
    static public function bufferOutput()
    {
        if (func_num_args() == 0) {
            return false;
        }

        $include = false;
        $args = func_get_args();
        $function = array_shift($args);

        if (is_array($function)) {
            if (!is_callable($function)) {
                return false;
            }
        } elseif (($function == 'include') ||
                  ($function == 'include_once') ||
                  ($function == 'require') ||
                  ($function == 'require_once')) {
            $include = true;
        } elseif (!function_exists($function)) {
            return false;
        }

        ob_start();
        if ($include) {
            $file = implode(',', $args);
            switch ($function) {
            case 'include':
                include $file;
                break;

            case 'include_once':
                include_once $file;
                break;

            case 'require':
                require $file;
                break;

            case 'require_once':
                require_once $file;
                break;
            }
        } else {
            call_user_func_array($function, $args);
        }

        return ob_get_clean();
    }

    /**
     * Checks to see if a value has been set by the script and not by GET,
     * POST, or cookie input. The value being checked MUST be in the global
     * scope.
     *
     * @param string $varname  The variable name to check.
     * @param mixed $default   Default value if the variable isn't present
     *                         or was specified by the user. Defaults to null.
     *
     * @return mixed  $default if the var is in user input or not present,
     *                the variable value otherwise.
     */
    static public function nonInputVar($varname, $default = null)
    {
        return (isset($_GET[$varname]) || isset($_POST[$varname]) || isset($_COOKIE[$varname]))
            ? $default
            : (isset($GLOBALS[$varname]) ? $GLOBALS[$varname] : $default);
    }

    /**
     * Adds a name=value pair to the end of an URL, taking care of whether
     * there are existing parameters and whether to use ?, & or &amp; as the
     * glue.  All data will be urlencoded.
     *
     * @param string $url       The URL to modify
     * @param mixed $parameter  Either the name value -or- an array of
     *                          name/value pairs.
     * @param string $value     If specified, the value part ($parameter is
     *                          then assumed to just be the parameter name).
     * @param boolean $encode   Encode the argument separator?
     *
     * @return string  The modified URL.
     */
    static public function addParameter($url, $parameter, $value = null,
                                        $encode = true)
    {
        if (empty($parameter)) {
            return $url;
        }

        if ($url instanceof Horde_Url) {
            $url->raw = !$encode;
            return $url->add($parameter, $value);
        }

        $horde_url = new Horde_Url($url, !$encode);
        return $horde_url->add($parameter, $value);
    }

    /**
     * Removes name=value pairs from a URL.
     *
     * @param string $url    The URL to modify.
     * @param mixed $remove  Either a single parameter to remove or an array
     *                       of parameters to remove.
     *
     * @return string  The modified URL.
     */
    static public function removeParameter($url, $remove)
    {
        $horde_url = new Horde_Url($url);

        if ($url instanceof Horde_Url) {
            return $url->remove($parameter);
        }

        return $horde_url->remove($remove);
    }

    /**
     * Returns a url with the 'nocache' parameter added, if the browser is
     * buggy and caches old URLs.
     *
     * @param string $url      The URL to modify.
     * @param boolean $encode  Encode the argument separator?
     *
     * @return string  The requested URI.
     */
    static public function nocacheUrl($url, $encode = true)
    {
        /* We may need to set a dummy parameter 'nocache' since some
         * browsers do not always honor the 'no-cache' header. */
        $browser = Horde_Browser::singleton();
        if ($browser->hasQuirk('cache_same_url')) {
            if (is_null(self::$_randnum)) {
                self::$_randnum = base_convert(microtime(), 10, 36);
            }
            return self::addParameter($url, 'nocache', self::$_randnum, $encode);
        }

        return $url;
    }

    /**
     * Returns a hidden form input containing the session name and id.
     *
     * @param boolean $append_session  0 = only if needed, 1 = always.
     *
     * @return string  The hidden form input, if needed/requested.
     */
    static public function formInput($append_session = 0)
    {
        return (($append_session == 1) || !isset($_COOKIE[session_name()]))
            ? '<input type="hidden" name="' . htmlspecialchars(session_name()) . '" value="' . htmlspecialchars(session_id()) . "\" />\n"
            : '';
    }

    /**
     * Prints a hidden form input containing the session name and id.
     *
     * @param boolean $append_session  0 = only if needed, 1 = always.
     */
    static public function pformInput($append_session = 0)
    {
        echo self::formInput($append_session);
    }

    /**
     * If magic_quotes_gpc is in use, run stripslashes() on $var.
     *
     * @param string &$var  The string to un-quote, if necessary.
     *
     * @return string  $var, minus any magic quotes.
     */
    static public function dispelMagicQuotes(&$var)
    {
        if (is_null(self::$_magicquotes)) {
            self::$_magicquotes = get_magic_quotes_gpc();
        }

        if (self::$_magicquotes) {
            if (!is_array($var)) {
                $var = stripslashes($var);
            } else {
                array_walk($var, array('Horde_Util', 'dispelMagicQuotes'));
            }
        }

        return $var;
    }

    /**
     * Gets a form variable from GET or POST data, stripped of magic quotes if
     * necessary. If the variable is somehow set in both the GET data and the
     * POST data, the value from the POST data will be returned and the GET
     * value will be ignored.
     *
     * @param string $var      The name of the form variable to look for.
     * @param string $default  The value to return if the variable is not
     *                         there.
     *
     * @return string  The cleaned form variable, or $default.
     */
    static public function getFormData($var, $default = null)
    {
        return (($val = self::getPost($var)) !== null)
            ? $val
            : self::getGet($var, $default);
    }

    /**
     * Gets a form variable from GET data, stripped of magic quotes if
     * necessary. This function will NOT return a POST variable.
     *
     * @param string $var      The name of the form variable to look for.
     * @param string $default  The value to return if the variable is not
     *                         there.
     *
     * @return string  The cleaned form variable, or $default.
     */
    static public function getGet($var, $default = null)
    {
        return (isset($_GET[$var]))
            ? self::dispelMagicQuotes($_GET[$var])
            : $default;
    }

    /**
     * Gets a form variable from POST data, stripped of magic quotes if
     * necessary. This function will NOT return a GET variable.
     *
     * @param string $var      The name of the form variable to look for.
     * @param string $default  The value to return if the variable is not
     *                         there.
     *
     * @return string  The cleaned form variable, or $default.
     */
    static public function getPost($var, $default = null)
    {
        return (isset($_POST[$var]))
            ? self::dispelMagicQuotes($_POST[$var])
            : $default;
    }

    /**
     * Determines the location of the system temporary directory.
     *
     * @return string  A directory name which can be used for temp files.
     *                 Returns false if one could not be found.
     */
    static public function getTempDir()
    {
        // First, try PHP's upload_tmp_dir directive.
        $tmp = ini_get('upload_tmp_dir');

        // Otherwise, try to determine the TMPDIR environment
        // variable.
        if (empty($tmp)) {
            $tmp = getenv('TMPDIR');
        }

        // If we still cannot determine a value, then cycle through a
        // list of preset possibilities.
        if (empty($tmp)) {
            foreach (self::$tmpLocations as $tmp_check) {
                if (@is_dir($tmp_check)) {
                    $tmp = $tmp_check;
                    break;
                }
            }
        }

        return empty($tmp) ? false : $tmp;
    }

    /**
     * Creates a temporary filename for the lifetime of the script, and
     * (optionally) registers it to be deleted at request shutdown.
     *
     * @param string $prefix   Prefix to make the temporary name more
     *                         recognizable.
     * @param boolean $delete  Delete the file at the end of the request?
     * @param string $dir      Directory to create the temporary file in.
     * @param boolean $secureDelete  If deleting the file, should we securely delete the
     *                               file by overwriting it with random data?
     *
     * @return string   Returns the full path-name to the temporary file.
     *                  Returns false if a temp file could not be created.
     */
    static public function getTempFile($prefix = '', $delete = true, $dir = '',
                                       $secureDelete = false)
    {
        $tempDir = (empty($dir) || !is_dir($dir))
            ? self::getTempDir()
            : $dir;

        if (empty($tempDir)) {
            return false;
        }

        $tempFile = tempnam($tempDir, $prefix);

        // If the file was created, then register it for deletion and return.
        if (empty($tempFile)) {
            return false;
        }

        if ($delete) {
            self::deleteAtShutdown($tempFile, true, $secureDelete);
        }
        return $tempFile;
    }

    /**
     * Creates a temporary filename with a specific extension for the lifetime
     * of the script, and (optionally) registers it to be deleted at request
     * shutdown.
     *
     * @param string $extension      The file extension to use.
     * @param string $prefix         Prefix to make the temporary name more
     *                               recognizable.
     * @param boolean $delete        Delete the file at the end of the request?
     * @param string $dir            Directory to create the temporary file in.
     * @param boolean $secureDelete  If deleting file, should we securely delete the
     *                               file by overwriting it with random data?
     *
     * @return string   Returns the full path-name to the temporary file.
     *                  Returns false if a temporary file could not be created.
     */
    static public function getTempFileWithExtension($extension = '.tmp', $prefix = '', $delete = true, $dir = '',
                                                    $secureDelete = false)
    {
        $tempDir = (empty($dir) || !is_dir($dir))
            ? self::getTempDir()
            : $dir;

        if (empty($tempDir)) {
            return false;
        }

        $windows = substr(PHP_OS, 0, 3) == 'WIN';
        $tries = 1;
        do {
            // Get a known, unique temporary file name.
            $sysFileName = tempnam($tempDir, $prefix);
            if ($sysFileName === false) {
                return false;
            }

            // tack on the extension
            $tmpFileName = $sysFileName . $extension;
            if ($sysFileName == $tmpFileName) {
                return $sysFileName;
            }

            // Move or point the created temporary file to the full filename
            // with extension. These calls fail if the new name already
            // exists.
            $fileCreated = ($windows ? @rename($sysFileName, $tmpFileName) : @link($sysFileName, $tmpFileName));
            if ($fileCreated) {
                if (!$windows) {
                    unlink($sysFileName);
                }

                if ($delete) {
                    self::deleteAtShutdown($tmpFileName, true, $secureDelete);
                }

                return $tmpFileName;
            }

            unlink($sysFileName);
            $tries++;
        } while ($tries <= 5);

        return false;
    }

    /**
     * Creates a temporary directory in the system's temporary directory.
     *
     * @param boolean $delete   Delete the temporary directory at the end of
     *                          the request?
     * @param string $temp_dir  Use this temporary directory as the directory
     *                          where the temporary directory will be created.
     *
     * @return string  The pathname to the new temporary directory.
     *                 Returns false if directory not created.
     */
    static public function createTempDir($delete = true, $temp_dir = null)
    {
        if (is_null($temp_dir)) {
            $temp_dir = self::getTempDir();
        }

        if (empty($temp_dir)) {
            return false;
        }

        /* Get the first 8 characters of a random string to use as a temporary
           directory name. */
        do {
            $new_dir = $temp_dir . '/' . substr(base_convert(mt_rand() . microtime(), 10, 36), 0, 8);
        } while (file_exists($new_dir));

        $old_umask = umask(0000);
        if (!mkdir($new_dir, 0700)) {
            $new_dir = false;
        } elseif ($delete) {
            self::deleteAtShutdown($new_dir);
        }
        umask($old_umask);

        return $new_dir;
    }

    /**
     * Returns the canonical path of the string.  Like PHP's built-in
     * realpath() except the directory need not exist on the local server.
     *
     * Algorithim loosely based on code from the Perl File::Spec::Unix module
     * (version 1.5).
     *
     * @param string $path  A file path.
     *
     * @return string  The canonicalized file path.
     */
    static public function realPath($path)
    {
        /* Standardize on UNIX directory separators. */
        if (!strncasecmp(PHP_OS, 'WIN', 3)) {
            $path = str_replace('\\', '/', $path);
        }

        /* xx////xx -> xx/xx
         * xx/././xx -> xx/xx */
        $path = preg_replace(array("|/+|", "@(/\.)+(/|\Z(?!\n))@"), array('/', '/'), $path);

        /* ./xx -> xx */
        if ($path != './') {
            $path = preg_replace("|^(\./)+|", '', $path);
        }

        /* /../../xx -> xx */
        $path = preg_replace("|^/(\.\./?)+|", '/', $path);

        /* xx/ -> xx */
        if ($path != '/') {
            $path = preg_replace("|/\Z(?!\n)|", '', $path);
        }

        /* /xx/.. -> / */
        while (strpos($path, '/..') !== false) {
            $path = preg_replace("|/[^/]+/\.\.|", '', $path);
        }

        return empty($path) ? '/' : $path;
    }

    /**
     * Removes given elements at request shutdown.
     *
     * If called with a filename will delete that file at request shutdown; if
     * called with a directory will remove that directory and all files in that
     * directory at request shutdown.
     *
     * If called with no arguments, return all elements to be deleted (this
     * should only be done by Horde_Util::_deleteAtShutdown()).
     *
     * The first time it is called, it initializes the array and registers
     * Horde_Util::_deleteAtShutdown() as a shutdown function - no need to do
     * so manually.
     *
     * The second parameter allows the unregistering of previously registered
     * elements.
     *
     * @param string $filename   The filename to be deleted at the end of the
     *                           request.
     * @param boolean $register  If true, then register the element for
     *                           deletion, otherwise, unregister it.
     * @param boolean $secure    If deleting file, should we securely delete
     *                           the file?
     */
    static public function deleteAtShutdown($filename, $register = true,
                                            $secure = false)
    {
        /* Initialization of variables and shutdown functions. */
        if (!self::$_shutdownreg) {
            register_shutdown_function(array('Horde_Util', 'shutdown'));
            self::$_shutdownreg = true;
        }

        $ptr = &self::$_shutdowndata;
        if ($register) {
            if (@is_dir($filename)) {
                $ptr['dirs'][$filename] = true;
            } else {
                $ptr['files'][$filename] = true;
            }

            if ($secure) {
                $ptr['secure'][$filename] = true;
            }
        } else {
            unset($ptr['dirs'][$filename], $ptr['files'][$filename], $ptr['secure'][$filename]);
        }
    }

    /**
     * Deletes registered files at request shutdown.
     *
     * This function should never be called manually; it is registered as a
     * shutdown function by Horde_Util::deleteAtShutdown() and called
     * automatically at the end of the request.
     *
     * Contains code from gpg_functions.php.
     * Copyright 2002-2003 Braverock Ventures
     */
    static public function shutdown()
    {
        $ptr = &self::$_shutdowndata;

        foreach ($ptr['files'] as $file => $val) {
            /* Delete files */
            if ($val && file_exists($file)) {
                /* Should we securely delete the file by overwriting the data
                   with a random string? */
                if (isset($ptr['secure'][$file])) {
                    $filesize = filesize($file);
                    $fp = fopen($file, 'r+');
                    foreach (self::$patterns as $pattern) {
                        $pattern = substr(str_repeat($pattern, floor($filesize / strlen($pattern)) + 1), 0, $filesize);
                        fwrite($fp, $pattern);
                        fseek($fp, 0);
                    }
                    fclose($fp);
                }
                @unlink($file);
            }
        }

        foreach ($ptr['dirs'] as $dir => $val) {
            /* Delete directories */
            if ($val && file_exists($dir)) {
                /* Make sure directory is empty. */
                $dir_class = dir($dir);
                while (false !== ($entry = $dir_class->read())) {
                    if ($entry != '.' && $entry != '..') {
                        @unlink($dir . '/' . $entry);
                    }
                }
                $dir_class->close();
                @rmdir($dir);
            }
        }
    }

    /**
     * Outputs javascript code to close the current window.
     *
     * @param string $code  Any additional javascript code to run before
     *                      closing the window.
     */
    static public function closeWindowJS($code = '')
    {
        echo "<script type=\"text/javascript\">//<![CDATA[\n" .
            $code .
            "window.close();\n//]]></script>\n";
    }

    /**
     * Caches the result of extension_loaded() calls.
     *
     * @param string $ext  The extension name.
     *
     * @return boolean  Is the extension loaded?
     */
    static public function extensionExists($ext)
    {
        if (!isset(self::$_cache[$ext])) {
            self::$_cache[$ext] = extension_loaded($ext);
        }

        return self::$_cache[$ext];
    }

    /**
     * Tries to load a PHP extension, behaving correctly for all operating
     * systems.
     *
     * @param string $ext  The extension to load.
     *
     * @return boolean  True if the extension is now loaded, false if not.
     *                  True can mean that the extension was already loaded,
     *                  OR was loaded dynamically.
     */
    static public function loadExtension($ext)
    {
        /* If $ext is already loaded, our work is done. */
        if (self::extensionExists($ext)) {
            return true;
        }

        /* See if we can call dl() at all, by the current ini settings. */
        if ((ini_get('enable_dl') != 1) || (ini_get('safe_mode') == 1)) {
            return false;
        }

        if (!strncasecmp(PHP_OS, 'WIN', 3)) {
            $suffix = 'dll';
        } else {
            switch (PHP_OS) {
            case 'HP-UX':
                $suffix = 'sl';
                break;

            case 'AIX':
                $suffix = 'a';
                break;

            case 'OSX':
                $suffix = 'bundle';
                break;

            default:
                $suffix = 'so';
            }
        }

        return @dl($ext . '.' . $suffix) || @dl('php_' . $ext . '.' . $suffix);
    }

    /**
     * Checks if all necessary parameters for a driver's configuration are set
     * and returns a PEAR_Error if something is missing.
     *
     * @param array $params   The configuration array with all parameters.
     * @param array $fields   An array with mandatory parameter names for this
     *                        driver.
     * @param string $name    The clear text name of the driver. If not
     *                        specified, the application name will be used.
     * @param array $info     A hash containing detailed information about the
     *                        driver. Will be passed as the userInfo to the
     *                        PEAR_Error.
     */
    static public function assertDriverConfig($params, $fields, $name,
                                              $info = array())
    {
        $info = array_merge($info, array(
            'params' => $params,
            'fields' => $fields,
            'name' => $name
        ));

        if (!is_array($params) || !count($params)) {
            return PEAR::throwError(sprintf(_("No configuration information specified for %s."), $name), self::HORDE_ERROR_DRIVER_CONFIG_MISSING, $info);
        }

        foreach ($fields as $field) {
            if (!isset($params[$field])) {
                return PEAR::throwError(sprintf(_("Required \"%s\" not specified in configuration."), $field, $name), self::HORDE_ERROR_DRIVER_CONFIG, $info);
            }
        }
    }

    /**
     * Returns a format string to be used by strftime().
     *
     * @param string $format  A format string as used by date().
     *
     * @return string  A format string as similar as possible to $format.
     */
    static public function date2strftime($format)
    {
        $f_len = strlen($format);
        $result = '';

        for ($pos = 0; $pos < $f_len;) {
            for ($symbol = 0, $symcount = count(self::$dateSymbols); $symbol < $symcount; ++$symbol) {
                if (strpos($format, self::$dateSymbols[$symbol], $pos) === $pos) {
                    $result .= self::$strftimeSymbols[$symbol];
                    $pos += strlen(self::$dateSymbols[$symbol]);
                    continue 2;
                }
            }
            $result .= substr($format, $pos, 1);
            ++$pos;
        }

        return $result;
    }

    /**
     * Returns a format string to be used by date().
     *
     * @param string $format  A format string as used by strftime().
     *
     * @return string  A format string as similar as possible to $format.
     */
    static public function strftime2date($format)
    {
        return str_replace(self::$strftimeSymbols, self::$dateSymbols, $format);
    }

    /**
     * Utility function to obtain PATH_INFO information.
     *
     * @return string  The PATH_INFO string.
     */
    static public function getPathInfo()
    {
        if (isset($_SERVER['PATH_INFO']) &&
            (strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') === false)) {
            return $_SERVER['PATH_INFO'];
        } elseif (isset($_SERVER['REQUEST_URI']) &&
                  isset($_SERVER['SCRIPT_NAME'])) {
            $search = array((basename($_SERVER['SCRIPT_NAME']) == 'index.php') ? dirname($_SERVER['SCRIPT_NAME']) . '/' : $_SERVER['SCRIPT_NAME']);
            $replace = array('');
            if (!empty($_SERVER['QUERY_STRING'])) {
                $search[] = '?' . $_SERVER['QUERY_STRING'];
                $replace[] = '';
            }
            return str_replace($search, $replace, $_SERVER['REQUEST_URI']);
        }

        return '';
    }

    /**
     * URL-safe base64 encoding, with trimmed '='.
     *
     * @param string $string  String to encode.
     *
     * @return string  URL-safe, base64 encoded data.
     */
    static public function uriB64Encode($string)
    {
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($string));
    }

    /**
     * Decode URL-safe base64 data, dealing with missing '='.
     *
     * @param string $string  Encoded data
     *
     * @return string  Decoded data.
     */
    static public function uriB64Decode($string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

}
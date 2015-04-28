<?php
/**
 * Cache
 *
 * @author Brett O'Donnell <cornernote@gmail.com>
 * @copyright 2013 Mr PHP
 * @link https://github.com/cornernote/php-cache-function
 * @license http://www.gnu.org/copyleft/gpl.html
 */

// prevent conflicts between different applications using the same memcache server
define('CACHE_NAMESPACE', 'my-cache-namespace');

// if memcache is not available this folder will be used to store cache
define('CACHE_FOLDER', '/tmp/cache/');

// memcache server hostname
define('CACHE_MEMCACHE_HOST', 'localhost');

// memcache server port
define('CACHE_MEMCACHE_PORT', '11211');

// Disable attempting to use a type of cache if you need to
// Otherwise we'll try and be smart and pick for you
define('USE_MEMCACHE',false);
define('USE_FILECACHE',true);

/**
 * cache()
 * Read, Write or Clear cached data using a key value pair.
 *
 * @param string $key - the key to use to store and retrieve the cached data
 * @param mixed $value - the data to store in cache
 * @param string $expires - the expiry time of the data
 * @return mixed - the cached data to return
 **/
function cache($key, $value = "", $expires = '+1 year')
{
    // static variables allowing the function to run faster when called multiple times
    static $cache_id, $memcache;

    $debug = 0;

    // get the cache_id used for easy cache clearing
    //if ($key != 'cache_id') {
    //    // If there is no static cache_id, generate a new one and store it
    //    if (!$cache_id) {
    //        cache('cache_id', null);

    //        $cache_id = md5(microtime(1));
    //        cache('cache_id', $cache_id);
    //    }
    //    $file = CACHE_NAMESPACE . '.' . $cache_id . '.' . $key;
    //} else {
    //    $file = CACHE_NAMESPACE . '.' . $key;
    //}

    $file = CACHE_NAMESPACE . '.' . $key;

    // set the expire time
    $now = time();
    if (!is_numeric($expires)) {
        $expires = strtotime($expires, $now);
    }

    // attempt connection to memcache
    if (USE_MEMCACHE && $memcache === null) {
        if (class_exists('Memcached')) {
            if (!$memcache) {
                $memcache = new Memcached;
                $memcache->addServer(CACHE_MEMCACHE_HOST,CACHE_MEMCACHE_PORT);
            }
        }
    }

    // handle cache using memcache
    if (USE_MEMCACHE && $memcache) {
        // read cache
        if ($value === "") {
            if ($debug) {
                print "Memcache: Read ($key)<br />";
            }
            $value = $memcache->get($file);
        // delete the cache
        } else if ($value === null) {
            if ($debug) {
                print "Memcache: Delete ($key)<br />";
            }
            $value = $memcache->delete($file);
        // write cache
        } elseif (isset($value)) {
            if ($debug) {
                print "Memcache: Set ($key / $value / $expires)<br />";
            }
            $value = $memcache->set($file, $value, $expires);
        // You should never get here
        } else {
            print "Error with cache(); (#19419)";
            exit;
        }
    // handle cache using files
    } elseif (USE_FILECACHE) {
        $md5  = md5($key);
        $dir  = CACHE_FOLDER . substr($md5, 0, 2) . '/' . substr($md5, 2, 2);
        $file = "$dir/$md5";

        // read cache
        if ($value === "") {
            if ($debug) {
                print "FileCache read \"$key\" ($file)<br />";
            }

            if (file_exists($file)) {
                $result = unserialize(file_get_contents($file));

                // If the data is expired
                if ($result['expires'] <= $now) {
                    $ok    = unlink($file);
                    $value = null;
                } else {
                    $value = $result['data'];
                }
            }
        // delete cache
        } elseif ($value === null) {
            if ($debug) {
                print "FileCache delete ($key)<br />";
            }

            // Remove the file if it's there
            if (file_exists($file)) {
                $value = unlink($file);
            } else {
                // For consistency with memcache we return false if there is no cache
                $value = false;
            }
        // write cache
        } else {
            if ($debug) {
                print "FileCache set ($key / $value / $expires)<br />";
            }

            $dir = dirname($file);

            if (!file_exists($dir)) {
                $ok = mkdir($dir, 0700, true);

                if (!$ok) {
                    trigger_error("Cannot create directory $dir", E_USER_WARNING);
                    return false;
                }
            }

            if (!is_writable($dir)) {
                trigger_error("Cache directory not writable $dir", E_USER_WARNING);
                return false;
            }

            $array = array(
                'key'     => $key,
                'data'    => $value,
                'expires' => $expires,
            );
            file_put_contents($file, serialize($array));
        }
    } else {
        trigger_error("No storage engines left to try", E_USER_ERROR);
    }

    // return the data
    return $value;
}

// vim: ai:ts=4:sw=4:expandtab

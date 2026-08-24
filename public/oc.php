<?php
if (function_exists('opcache_reset')) {
    echo "OPcache reset: " . (opcache_reset() ? "OK - all cache cleared" : "FAILED");
} elseif (function_exists('opcache_invalidate')) {
    echo "OPcache invalidate: " . (opcache_invalidate(__FILE__, true) ? "OK" : "FAILED");
} else {
    echo "OPcache not available";
}

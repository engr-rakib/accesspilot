<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache reset OK';
} else {
    echo 'OPcache not available';
}

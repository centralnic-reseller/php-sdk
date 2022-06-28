<?php

#declare(strict_types=1);

/**
 * MYCUSTOMNAMESPACE
 * Copyright © MYCUSTOMNAMESPACE
 */

namespace MYCUSTOMNAMESPACE;

/**
 * MYCUSTOMNAMESPACE Logger
 *
 * @package MYCUSTOMNAMESPACE
 */

class Logger implements \CNIC\LoggerInterface
{
    /**
     * output/log given data
     * @param string $post post request data in string format
     * @param \CNIC\HEXONET\Response $r Response to log
     * @param string|null $error error message
     */
    public function log($post, $r, $error = null): void
    {
        // apply your custom logging / output here
    }
}

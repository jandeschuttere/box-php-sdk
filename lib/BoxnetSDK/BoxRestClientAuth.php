<?php

namespace BoxnetSDK;

/**
 * Class BoxRestClientAuth.
 *
 * @package BoxnetSDK
 */
class BoxRestClientAuth
{
    /**
     * This is the method that is called whenever an authentication token is
     * received.
     *
     * @param string $authToken
     * @return $authToken
     */
    public function store($authToken) {
        return $authToken;
    }
}
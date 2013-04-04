<?php

namespace BoxnetSDK;

/**
 * The ReST Client is really what powers the entire class. It provides access
 * the the basic HTTP verbs that are currently supported by Box
 * @author Angelo R.
 *
 * @package BoxnetSDK
 */
class RestClient
{
    /**
     *
     * Perform any get type operation on a url
     * @param string $url
     * @return string The resulting data from the get operation.
     */
    public static function get($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    /**
     * Perform any post type operation on a url.
     * @param string $url
     * @param array $params A list of post-based params to pass.
     * @return mixed
     */
    public static function post($url, array $params = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }
}
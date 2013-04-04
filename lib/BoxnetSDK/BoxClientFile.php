<?php

namespace BoxnetSDK;

/**
 * Instead of returning a giant array of things for you to deal with, I've pushed
 * the array into two classes. The results are either a folder or a file, and each
 * has its own class.
 *
 * The BoxClientFile class will contain the attributes and tags that belong
 * to a single file. In addition, I've provided a series of CRUD operations that can
 * be performed on a file.
 * @author Angelo R
 *
 * @package BoxnetSDK
 */
class BoxClientFile
{
    private $attr;
    private $tags;

    /**
     * During construction, you can specify a path to a file and a file name. This
     * will prep the BoxClientFile instance for an upload. If you do not wish
     * to upload a file, simply instantiate this class without any attributes.
     *
     * If you want to fill this class with the details of a specific file, then
     * get_file_info and it will be imported into its own BoxClientFile class.
     *
     * @param string $pathToFile
     * @param string $filename
     */
    public function __construct($pathToFile = '', $filename = '')
    {
        $this->attr = array();

        if (!empty($pathToFile)) {
            $this->attr('localpath', $pathToFile);

            if (!empty($filename)) {
                $this->attr('filename', $filename);
            }
        }

    }

    /**
     * Imports the file attributes and tags. At some point we can add further
     * methods to make this a little more useful (a json method perhaps?)
     * @param array $file
     */
    public function import(array $file)
    {
        foreach ($file['@attributes'] as $key => $val) {
            $this->attr[$key] = $val;
        }

        if (array_key_exists('tags', $file)) {
            foreach($file['tags'] as $i => $tag) {
                $tags[$i] = $tag;
            }
        }
    }

    /**
     * Gets or sets file attributes, for a complete list of attributes please check the info object (get_file_info).
     * @param string $key
     * @param mixed $value
     */
    public function attr($key, $value = '')
    {
        if (empty($value) && array_key_exists($key, $this->attr)) {
            return $this->attr[$key];
        } else {
            $this->attr[$key] = $value;
        }
    }

    public function tag()
    {

    }

    /**
     * The download link to a particular file. You will need to manually pass in
     * the authentication token for the download link to work.
     *
     * @param BoxRestClient $boxNet A reference to the client library.
     * @param integer $version The version number of the file to download, leave blank if you want to download the latest version.
     * @return string $url The url link to the download
     */
    public function download_url(BoxRestClient $boxNet, $version = 0)
    {
        $url = $boxNet->baseUrl.'/'.$boxNet->apiVersion;
        if ($version == 0) {
            // Not a specific version download
            $url .= '/download/'.$boxNet->authToken.'/'.$this->attr('id');
        } else {
            // Downloading a certain version
            $url .= '/download_version/'.$boxNet->authToken.'/'.$this->attr('id').'/'.$version;
        }

        return $url;
    }
}
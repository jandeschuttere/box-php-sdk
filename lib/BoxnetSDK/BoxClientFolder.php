<?php

namespace BoxnetSDK;

/**
 * Instead of returning a giant array of things for you to deal with, I've pushed
 * the array into two classes. The results are either a folder or a file, and each
 * has its own class.
 *
 * The BoxClientFolder class will contain an array of files, but will also have
 * its own attributes. In addition. I've provided a series of CRUD operations that
 * can be performed on a folder.
 * @author Angelo R
 *
 * @package BoxnetSDK
 */
class BoxClientFolder
{
    private $attr;

    public $file;
    public $folder;

    public function __construct()
    {
        $this->attr = array();
        $this->file = array();
        $this->folder = array();

    }

    /**
     * Acts as a getter and setter for various attributes.
     *
     * You should know the name of the attribute that you are trying to access.
     * @param string $key
     * @param mixed $value
     * @return mixed|void
     */
    public function attr($key, $value = '')
    {
        if (empty($value) && array_key_exists($key, $this->attr)) {
            return $this->attr[$key];
        } else {
            $this->attr[$key] = $value;
        }
    }

    /**
     * Imports the tree structure and allows us to provide some extended functionality
     * at some point. Don't run import manually. It expects certain things that are
     * delivered through the API. Instead, if you need a tree structure of something,
     * simply call Box_Rest_Client->folder(folder_id); and it will automatically return
     * the right stuff.
     *
     * Due to an inconsistency with the Box.net ReST API, this section involves a few
     * more checks than normal to ensure that all the necessary values are available
     * when doing the import.
     * @param array $tree
     */
    public function import(array $tree)
    {
        foreach ($tree['@attributes'] as $key => $val) {
            $this->attr[$key] = $val;
        }

        if (array_key_exists('folders', $tree)) {
            if (array_key_exists('folder', $tree['folders'])) {

                if (array_key_exists('@attributes', $tree['folders']['folder'])) {
                    // This is the case when there is a single folder within the root
                    $boxFolder = new BoxClientFolder;
                    $boxFolder->import($tree['folders']['folder']);
                    $this->folder[] = $boxFolder;

                } else {
                    // This is the case when there are multiple folders within the root
                    foreach($tree['folders']['folder'] as $i => $folder) {
                        $boxFolder = new BoxClientFolder;
                        $boxFolder->import($folder);
                        $this->folder[] = $boxFolder;
                    }
                }

            }
        }

        if (array_key_exists('files', $tree)) {
            if (array_key_exists('file', $tree['files'])) {

                if (array_key_exists('@attributes', $tree['files']['file'])) {
                    // This is the case when there is a single file within a directory
                    $boxFile = new BoxClientFile;
                    $boxFile->import($tree['files']['file']);
                    $this->file[] = $boxFile;

                } else {
                    // This is the case when there are multiple files in a directory
                    foreach($tree['files']['file'] as $i => $file) {
                        $boxFile = new BoxClientFile;
                        $boxFile->import($file);
                        $this->file[] = $boxFile;
                    }
                }

            }
        }
    }
}
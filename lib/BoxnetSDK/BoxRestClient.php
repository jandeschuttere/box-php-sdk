<?php

namespace BoxnetSDK;

/**
 * This is the main API class. This is what you will be invoking when you are dealing with the
 * API.
 *
 * I would suggest reading up the example.php file instead of trying to peruse through this
 * file as it's a little much to take in at once. The example.php file provides you the basics
 * of getting started.
 *
 * If you want to inspect what various api-calls will return check out inspector.php which
 * provides a nice little interface to do just that.
 *
 * That being said, here's a quick intro to how to use this class.
 *
 * - If you are utilizing it on more than one page, definitely set the apiKey within the class.
 *   It will save you a lot of time. I am going to assume that you did just that.
 * - I am assuming that you have !NOT! configured the BoxRestClientAuth->store() method and it is default.
 *   Therefore, it will just return the authToken.
 *
 * $box_rest_client = new Box_Rest_Client();
 * if(!array_key_exists('auth',$_SESSION) || empty($_SESSION['auth']) {
 *     $box_rest_client->authenticate();
 * } else {
 *     $_SESSION['auth'] = $box_rest_client->authenticate();
 * }
 *
 * $box_rest_client->folder(0);
 *
 * The above code will give you a nice little tree-representation of your files.
 *
 * For more in-depth examples, either take a look at the example.php file or check out
 * inspector/index.php
 *
 * @todo Proper SSL support
 *
 * The current SSL setup is a bit of a hack. I've just disabled SSL verification on cURL.
 * Instead, the better idea would be to implement something like this at some point:
 * @link http://unitstep.net/blog/2009/05/05/using-curl-in-php-to-access-https-ssltls-protected-sites/
 *
 * @todo File Manipulation
 * @todo Folder Manipulation
 *
 * @author Angelo R
 *
 * @package BoxnetSDK
 */
class BoxRestClient
{
    public $apiKey;
    public $ticket;
    public $authToken;

    public $apiVersion = '1.0';
    public $baseUrl    = 'https://www.box.net/api';
    public $uploadUrl  = 'https://upload.box.net/api';

    // Not implemented yet sadly..
    public $mobile = false;

    /**
     * You need to create the client with the API KEY that you received when
     * you signed up for your apps.
     *
     * @param string $apiKey
     * @throws BoxRestClientException If the api-key was not set.
     */
    public function __construct($apiKey = '')
    {
        if (empty($this->apiKey) && empty($apiKey)) {
            throw new BoxRestClientException(
                'Invalid API Key. Please provide an API Key when creating an instance of the class,
                 or by setting BoxRestClient->apiKey'
            );
        } else {
            $this->apiKey = (empty($apiKey)) ? $this->apiKey : $apiKey;
        }
    }

    /**
     * Because the authentication method is an odd one, I've provided a wrapper for
     * it that should deal with either a mobile or standard web application. You
     * will need to set the callback url from your application on the developer
     * website and that is called automatically.
     *
     * When this method notices the "authToken" query string, it will automatically
     * call the BoxRestClientAuth->store() method. You can do whatever you want
     * with it in that method. I suggest you read the bit of documentation that will
     * be present directly above the class.
     */
    public function authenticate()
    {
        if (array_key_exists('auth_token', $_GET)) {
            $this->authToken = $_GET['auth_token'];

            $boxRestClientAuth = new BoxRestClientAuth();
            return $boxRestClientAuth->store($this->authToken);
        } else {
            $res = $this->get('get_ticket', array('api_key' => $this->apiKey));

            if($res['status'] === 'get_ticket_ok') {
                $this->ticket = $res['ticket'];

                if($this->mobile) {
                    header('location: https://m.box.net/api/1.0/auth/'.$this->ticket);
                } else {
                    header('location: https://www.box.net/api/1.0/auth/'.$this->ticket);
                }
            }
            else {
                throw new BoxRestClientException($res['status']);
            }
        }
    }

    /**
     * This folder method is provided as it tends to be what a lot of people will most
     * likely try to do. It returns a list of folders/files utilizing our
     * BoxClientFolder and BoxClientFile classes instead of the raw tree array
     * that is normally returned.
     *
     * You can totally ignore this and instead rely entirely on get/post and parse the
     * tree yourself if it doesn't quite do what you want.
     *
     * The default params ensure that the tree is returned as quickly as possible. Only
     * the first level is returned and only in a simple format.
     *
     * @param integer $root The root directory that you want to load the tree from.
     * @param array $params Any additional params you want to pass, comma separated.
     * @return BoxClientFolder
     */
    public function folder($root, $params = array('params' => array('nozip', 'onelevel', 'simple')))
    {
        $params['folder_id'] = $root;
        $res = $this->get('get_account_tree', $params);

        $folder = new BoxClientFolder();
        if (array_key_exists('tree', $res)) {
            $folder->import($res['tree']['folder']);
        }
        return $folder;
    }

    /**
     *
     * Since we provide a way to get information on a folder, it's only fair that we
     * provide the same interface for a file. This will grab the info for a file and
     * push it back as a BoxClientFile. Note that this method (for some reason)
     * gives you less information than if you got the info from the tree view.
     *
     * @param integer $fileId
     * @return BoxClientFile
     */
    public function file($fileId)
    {
        $res = $this->get('get_file_info', array('file_id' => $fileId));

        // For some reason the Box.net api returns two different representations
        // of a file. In a tree view, it returns the more attributes than
        // in a standard get_file_info view. As a result, we'll just trick the
        // implementation of import in BoxClientFile.
        $res['@attributes'] = $res['info'];
        $file = new BoxClientFile;
        $file->import($res);
        return $file;
    }

    /**
     *
     * Creates a folder on the server with the specified attributes.
     * @param BoxClientFolder $folder
     */
    public function create(BoxClientFolder &$folder)
    {
        $params = array(
            'name'      => $folder->attr('name'),
            'parent_id' => intval($folder->attr('parent_id')),
            'share'     => intval($folder->attr('share'))
        );
        $res = $this->post('create_folder',$params);
        if ($res['status'] == 'create_ok') {
            foreach ($res['folder'] as $key => $val) {
                $folder->attr($key,$val);
            }
        }

        return $res['status'];
    }

    /**
     * Returns the url to upload a file to the specified parent folder.
     *
     * Beware!
     * If you screw up the type the upload will probably still go through properly
     * but the results may be unexpected. For example, uploading and overwriting a
     * end up doing two very different things if you pass in the wrong kind of id
     * (a folder id vs a file id).
     *
     * For the right circumstance to use each type of file, check this:
     * @link http://developers.box.net/w/page/12923951/ApiFunction_Upload%20and%20Download
     *
     * @param string  $type One of upload | overwrite | new_copy.
     * @param integer $id   The id of the file or folder that you are uploading to.
     * @return string
     */
    public function uploadUrl($type = 'upload', $id = 0)
    {
        $urlTemplate = $this->uploadUrl . '/' . $this->apiVersion . '/%s/' . $this->authToken . '/' . $id;

        switch (strtolower($type)) {
            case 'upload':
                $url = sprintf($urlTemplate, 'upload');
                break;
            case 'overwrite':
                $url = sprintf($urlTemplate, 'overwrite');
                break;
            case 'new_copy':
                $url = sprintf($urlTemplate, 'new_copy');
                break;
            default:
                $url = '';
                break;
        }

        return $url;
    }

    /**
     * Uploads the file to the specified folder.
     *
     * You can set the parent_id attribute on the file for this to work.
     *
     * Be careful!
     * Because of how the API currently works:
     * If you upload a file for the first time, but a file of that name already exists in that location,
     * this will automatically overwrite it.
     *
     * Be warned!
     * If you use this method of file uploading: the file WILL BOUNCE!
     * This means that the file will FIRST be uploaded to your servers and then it will be uploaded to Box.
     * If you want to bypass your server, call the "uploadUrl" method instead.
     *
     * @param BoxClientFile $file
     * @param array         $params           A list of valid input params can be found at the Download and upload method list
     * @param boolean       $uploadThenDelete If set, it will delete the file if the upload was successful
     * @throws BoxRestClientException If renaming an uploaded file was unsuccessful.
     * @return string
     * @link http://developers.box.net
     */
    public function upload(BoxClientFile &$file, array $params = array(), $uploadThenDelete = false)
    {
        if (array_key_exists('new_copy', $params) && $params['new_copy'] && intval($file->attr('id')) !== 0) {
            // This is a valid file for new copy, we can new_copy
            $url = $this->uploadUrl('new_copy',$file->attr('id'));

        } elseif (intval($file->attr('file_id')) !== 0 && !$new_copy) {
            // This file is overwriting another
            $url = $this->uploadUrl('overwrite',$file->attr('id'));

        } else {
            // This file is a new upload
            $url = $this->uploadUrl('upload',$file->attr('folder_id'));
        }

        // Assign a file name during construction OR by setting `$file->attr('filename');` manually
        $split = explode(DIRECTORY_SEPARATOR, $file->attr('localpath'));
        $split[count($split)-1] = $file->attr('filename');
        $newLocalPath = implode('\\',$split);

        // only rename if the old filename and the new filename are different
        if ($file->attr('localpath') != $newLocalPath) {
            if (!rename($file->attr('localpath'), $newLocalPath)) {
                throw new BoxRestClientException('Uploaded file could not be renamed.');

            } else {
                $file->attr('localpath', $newLocalPath);
            }
        }

        $params['file'] = '@'.$file->attr('localpath');

        $res = RestClient::post($url, $params);


        // This exists because the API returns malformed xml.. as soon as the API
        // is fixed it will automatically check against the parsed XML instead of
        // the string. When that happens, there will be a minor update to the library.
        $failedCodes = array(
            'wrong auth token',
            'application_restricted',
            'upload_some_files_failed',
            'not_enough_free_space',
            'filesize_limit_exceeded',
            'access_denied',
            'upload_wrong_folder_id',
            'upload_invalid_file_name'
        );

        if (in_array($res,$failedCodes)) {
            return $res;
        } else {
            $res = $this->parseResult($res);
        }
        // Only import if the status was successful
        if ($res['status'] == 'upload_ok') {
            $file->import($res['files']['file']);

            // Only delete if the upload was successful and the developer requested it
            if ($uploadThenDelete) {
                unlink($file->attr('localpath'));
            }
        }

        return $res['status'];
    }

    /**
     * Executes an api function using get with the required opts.
     *
     * It will attempt to execute it regardless of whether or not it exists.
     *
     * @param string $api
     * @param array $opts
     * @return mixed
     */
    public function get($api, array $opts = array())
    {
        $opts = $this->setOptions($opts);
        $url = $this->buildUrl($api,$opts);

        $data = RestClient::get($url);

        return $this->parseResult($data);
    }

    /*
     * Executes an api function using post with the required options.
     *
     * It will attempt to execute it regardless of whether or not it exists.
     *
     * @param string $api
     * @param array $opts
     */
    public function post($api, array $params = array(), array $opts = array())
    {
        $opts = $this->setOptions($opts);
        $url = $this->buildUrl($api,$opts);

        $data = RestClient::post($url,$params);
        return $this->parseResult($data);
    }

    /**
     * To minimize having to remember things, get/post will automatically call this method to set some default values
     * as long as the default values don't already exist.
     *
     * @param array $options
     * @return array
     */
    private function setOptions(array $options)
    {
        if (!array_key_exists('api_key',$options)) {
            $options['api_key'] = $this->apiKey;
        }

        if (!array_key_exists('auth_token',$options)) {
            if (isset($this->authToken) && !empty($this->authToken)) {
                $options['auth_token'] = $this->authToken;
            }
        }

        return $options;
    }

    /**
     * Build the final api url that we will be curling, this will allow us to get the results needed.
     *
     * @param string $apiFunction
     * @param array $options
     * @return string
     */
    private function buildUrl($apiFunction, array $options)
    {
        $base = $this->baseUrl.'/'.$this->apiVersion.'/rest';
        $base .= '?action='.$apiFunction;

        foreach ($options as $key=>$val) {
            if (is_array($val)) {
                foreach ($val as $i => $v) {
                    $base.= '&'.$key.'[]='.$v;
                }
            } else {
                $base .= '&'.$key.'='.$val;
            }
        }

        return $base;
    }

    /**
     * Converts the XML we received into an array for easier messing with.
     *
     * Obviously this is a cheap hack and a few things are probably lost along the way (types for example),
     * but to get things up and running quickly, this works quite well.
     *
     * @param string $res
     * @return array
     */
    private function parseResult($res)
    {
        $xml    = simplexml_load_string($res);
        $json   = json_encode($xml);
        $array  = json_decode($json, true);

        return $array;
    }
}
<?php

/**
 * This class allows to keep in sync a the post files with a dropbox folder
 */

class DropboxStorage {

    /**
     * Dropbox API key
     *
     * @var $key
     */
    var $key;

    /**
     * Dropbox API secret
     *
     * @var $secret
     */
    var $secret;

    /**
     * Dropbox Encrypter key
     *
     * @var $encryptionKey
     */
    var $encryptionKey;

    /**
     * Path where the files will be synched
     *
     * @var $path
     */
    var $path;

    /**
     * Database settings
     *
     * @var $dbSettings
     */
     var $dbSettings;

    /**
     * Class constructor sets the default values
     *
     * @param string $app
     * @param array $dbSettings
     * @param array $dropboxSettings
     */
    public function __construct($path, $dbSettings, $dropboxSettings) {
        $this->path = $path;
        $this->key = $dropboxSettings['key'];
        $this->secret = $dropboxSettings['secret'];
        $this->encryptionKey = $dropboxSettings['encrypter'];
        $this->dbSettings = $dbSettings;
    }

    /**
     * Connect the pdo storage
     *
     * @param int $id
     */
     public function connectStorage($id) {
         $encrypter = new \Dropbox\OAuth\Storage\Encrypter($this->encryptionKey);
         $this->storage = new \Dropbox\OAuth\Storage\PDO($encrypter, $id);
         $this->storage->connect(
            $this->dbSettings['host'],
            $this->dbSettings['database'],
            $this->dbSettings['username'],
            $this->dbSettings['password'],
            $this->dbSettings['port']
        );
     }

    /**
     * delete auth key for the user id specified
     *
     * @param int $id
     */
    public function delete($id) {
        $this->connectStorage($id);
        $this->storage->delete();
    }

    /**
     * Sync the Dropbox folder with the local folder
     *
     * @param string $name
     * @param int $id
     */
    public function sync($name, $id) {
        // Check whether to use HTTPS and set the callback URL
        $protocol = (!empty($_SERVER['HTTPS'])) ? 'https' : 'http';
        $callback = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $this->connectStorage($id);

        // Create the consumer and API objects
        $OAuth = new \Dropbox\OAuth\Consumer\Curl($this->key, $this->secret, $this->storage, $callback);
        $dropbox = new \Dropbox\API($OAuth);
        
        if(!file_exists($this->path . DIRECTORY_SEPARATOR . $name)) {
            mkdir($this->path . DIRECTORY_SEPARATOR . $name);
        }
        $hashFile = $this->path . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'dropbox.hash';

        if(file_exists($hashFile)){
            $dropboxHash = unserialize(file_get_contents($hashFile));
        }
        else{
            $dropboxHash['metadata'] = false;
            $dropboxHash['posts'] = array();
        }

        $metadata = $dropbox->metaData(null, null, 10000, $dropboxHash['metadata']);
        if($metadata['code']=='200') {
            if($dropboxHash['metadata'] != $metadata['body']->hash) {
                $dropboxFiles = array();
                foreach($metadata['body']->contents as $file) {
                    $fname = trim($file->path, '/');
                    $dropboxFiles[] = $fname;
                    if(!$file->is_dir && pathinfo($file->path, PATHINFO_EXTENSION) == 'md' && 
                    (!isset($dropboxHash['posts'][$fname]) || $dropboxHash['posts'][$fname] != $file->rev)) {
                        $f = $dropbox->getFile($file->path, $this->path . '/' . $name . '/' . $fname);
                        $dropboxHash['posts'][$fname] = $file->rev;
                    }
                }
                $dropboxHash['metadata'] = $metadata['body']->hash;
            }

            $dir = new DirectoryIterator($this->path . '/' . $name);
            foreach($dir as $fileInfo) {
                if($fileInfo->isFile()) {
                    if(!in_array(basename($fileInfo->getPathname()), $dropboxFiles)) {
                        unlink($fileInfo->getPathname());
                    }
                }
            }
            file_put_contents($hashFile, serialize($dropboxHash));
        }
    }
}

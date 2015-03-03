<?php

class FileBlogEngine
{

    /**
     * Path where the posts files are stored
     *
     * @var $path
     */
     var $path;

    /**
     * Id of the current blog
     *
     * @var $current
     */
    var $current;

    /**
     * BlogEngine constructor
     */
    public function __construct($path) {
        $this->path = $path;
        $this->current = 0;
    }

    /**
     * Get all post files from the specified path and return them in an array format
     *
     * @param string $blogName
     * @return array
     */
    function getPosts($blogName) {
        $postsPath = $this->path. DIRECTORY_SEPARATOR . $blogName . DIRECTORY_SEPARATOR;
        $postsDump =  $postsPath . 'posts.dump';

        if(file_exists($postsDump)) {
            $rawPosts = unserialize(file_get_contents($postsDump));
        }
        else {
            $rawPosts = array();
            $dir = new DirectoryIterator($postsPath);
            foreach($dir as $fileInfo) {
                if($fileInfo->isFile()) {
                    $post = $this->parsePost($blogName, $fileInfo->getPathname());
                    if(is_array($post)) {
                        $rawPosts[] = $post;
                    }
                }
            }
            usort($rawPosts, function($a, $b) {
                $dateA = strtotime($a['meta']['date']);
                $dateB = strtotime($b['meta']['date']);
                if($dateA == $dateB) return 0;
                else return ($dateA < $dateB)? 1 : -1;
            });
            file_put_contents($postsDump, serialize($rawPosts));
        }
        return $rawPosts;
    }

    /**
     * Check if the current post exists in a file and return it as an array
     * or return null if the post file does not exist or is not valid.
     *
     * @param string $blogName
     * @param string $filename
     * @param boolean $getBody
     * @return array || null
     */
    public function parsePost($blogName, $filename) {
        $filename = $this->path. DIRECTORY_SEPARATOR . $blogName
        . DIRECTORY_SEPARATOR . basename($filename);

        if(!file_exists($filename) || !is_file($filename) || pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
            return null;
        }

        $post = array('meta'=>'', 'body'=>'');
        $handle = fopen($filename, "r");
        if ($handle) {
            // get post metadata
            while(($buffer = fgets($handle)) !== false && trim($buffer) !== '') {
                $post['meta'] .= $buffer;
            }
            try {
                $post['meta'] = \Symfony\Component\Yaml\Yaml::parse($post['meta']);
            } catch(\Symfony\Component\Yaml\Exception\ParseException $e) {
                fclose($handle);
                return null;
            }
            if(!is_array($post['meta'])) {
                $post['meta'] = array();
                fseek($handle, 0);
            }

            $post['body'] = stream_get_contents($handle);
            $markdownParser = new dflydev\markdown\MarkdownParser();
            $post['body'] = $markdownParser->transformMarkdown(trim($post['body']));
            fclose($handle);
        }
        if(isset($post['meta']['published']) && $post['meta']['published'] == false) {
            return null;
        }
        return $this->setDefaultValues($blogName, $post, $filename);
    }

    /**
     * return a blog post loaded from a file specified in $filename
     *
     * @param string $blogName
     * @param string $filename
     * @return array || null
     */
    public function getPost($blogName, $filename) {
        $rawPosts = $this->getPosts($blogName);
        foreach($rawPosts as $key => $post) {
            if($post['meta']['filename'] == basename($filename)) {
                $this->current = $key;
                return $rawPosts[$this->current];
            }
        }
        return null;
    }

    /**
     * return the slug to the next post
     *
     * @param string $blogName
     * @return string || null
     */
    public function getNext($blogName) {
        $rawPosts = $this->getPosts($blogName);
        return isset($rawPosts[($this->current + 1)])? $rawPosts[($this->current + 1)]['meta']['slug'] : null;
    }

    /**
     * return the slug to the previous post
     *
     * @param string $blogName
     * @return string || null
     */
     public function getPrev($blogName) {
         $rawPosts = $this->getPosts($blogName);
         return isset($rawPosts[($this->current - 1)])? $rawPosts[($this->current - 1)]['meta']['slug'] : null;
     }

    /**
     * Set default values for posts in case link, date or title are not set.
     *
     * @param string $blogName
     * @param array $post
     * @return array
     */
    protected function setDefaultValues($blogName, $post, $filename) {
        // set default metadata values
        $post['meta'] = array_change_key_case($post['meta'], CASE_LOWER);
        $post['meta']['filename'] = basename($filename);
        $post['meta']['slug'] = substr($post['meta']['filename'], 0, strrpos(basename($filename), '.'));
        if(!isset($post['meta']['title'])) {
            $post['meta']['title'] = ucfirst(str_replace(array('_', '-', '.'), ' ',$post['meta']['slug']));
        }
        if(!isset($post['meta']['date']) || !strtotime($post['meta']['date'])) {
            $post['meta']['date'] = date('Y-m-d H:i', filemtime($filename));
        }
        return $post;
    }

    /**
     * delete cache files for a blog
     *
     * @param string $blogName
     */
     public function delete($blogName) {
         $blogPath = $this->path . DIRECTORY_SEPARATOR . $blogName;
         $rawPosts = array();
         $dir = new DirectoryIterator($blogPath);
         foreach($dir as $fileInfo) {
             if($fileInfo->isFile()) {
                 unlink($fileInfo->getPathname());
            }
         }
         rmdir($blogPath);
     }

     /**
      * create a new blog folder
      *
      * @param string $blogName
      */
      public function addBlog($blogName) {
          mkdir($this->path . DIRECTORY_SEPARATOR . basename($blogName));
      }
}

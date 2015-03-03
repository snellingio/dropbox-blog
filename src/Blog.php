<?php

/**
 * This class manages the blogs in the database
 */

class Blog
{

    /**
     * PDO connection
     *
     * @var $pdo
     */
    var $pdo;

    /**
     * Class constructor sets default values and creates a new PDO connection
     */
    public function __construct($settings) {
        $dsn = 'mysql:host='.$settings['host'].';port='.$settings['port'].';dbname=' . $settings['database'];
        $this->pdo = new \PDO($dsn, $settings['username'], $settings['password']);
    }

    /**
     * create a new blog with a unique name and return it's id
     *
     * @param string $blogName
     * @return int
     */
    public function addBlog($blogName) {
        $query = 'INSERT INTO blogs (name) VALUES (?);';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array($blogName));
        return $this->pdo->lastInsertId();
    }

    /**
     * return the id of the blog specified by name
     *
     * @param string $blogName
     * @return int || false
     */
    public function getByName($blogName) {
        $query = 'SELECT id, name FROM blogs WHERE name = ? LIMIT 1';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array($blogName));
        if ($result = $stmt->fetch()) {
            return $result['id'];
        }
        return false;
    }

    /**
     * return the name of the blog specified by the id
     *
     * @param int $id
     * @return string || false
     */
    public function getById($id) {
        $query = 'SELECT id, name FROM blogs WHERE id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array($id));
        if ($result = $stmt->fetch()) {
            return $result['name'];
        }
        return false;
    }

    /**
     * get a blog by Dropbox UID
     *
     * @param string $uid
     * @return boolean || false
     */
    public function getByUID($uid) {
        $query = 'SELECT id, name FROM blogs WHERE uid = ? LIMIT 1';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array($uid));
        if ($result = $stmt->fetch()) {
            return $result;
        }
        return false;
    }

    /**
     * set the Dropbox UID to the blog
     *
     * @param int $id
     * @param string $uid
     * @return boolean
     */
    public function setUID($id, $uid) {
        $query = 'UPDATE blogs set uid = ? where id = ?;';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array($uid, $id));
        return true;
    }

    /**
     * delete a blog by id
     *
     * @param int id
     * @return boolean
     */
    public function delete($id) {
        $query = 'DELETE FROM blogs WHERE id = ?;';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array($id));
        return true;
    }
}

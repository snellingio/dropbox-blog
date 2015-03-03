<?php

$app->get('/', function() use ($app) {
    if(isset($_SESSION['blog_id']) && $uid = $app->request()->get('uid')) {
        if($exists = $app->config('blogDB')->getByUID($uid)) {
            $app->config('blogDB')->delete($exists['id']);
            $app->config('dropboxStorage')->delete($exists['id']);
            $app->config('blogEngine')->delete($exists['name']);
        }
        $app->config('blogDB')->setUID($_SESSION['blog_id'], $uid);
        $name = $_SESSION['blog_name'];
        unset($_SESSION['blog_id']);
        unset($_SESSION['blog_name']);
        $app->redirect($app->urlFor('blogIndex', array('name'=>$name)));
    }
    $app->render('index.html');
})->name('home');

/**
 * create a new blog with the user submited data
 */
$app->post('/', function() use ($app) {
    $name = $app->request()->post('name');
    if(preg_match('/^[A-Z,a-z,0-9,_]+$/', $name)) {
        $exists = $app->config('blogDB')->getByName($name);

        if(!$exists) {
            try {
                $_SESSION['blog_id'] = $app->config('blogDB')->addBlog($name);
                $app->config('blogEngine')->addBlog($name);
                $_SESSION['blog_name'] = $name;
                $app->config('dropboxStorage')->sync($name, $_SESSION['blog_id']);
            } catch (\Dropbox\Exception $e) {
                $app->getLog()->error($e->getCode() . ' : ' . $e->getMessage());
            }
        }
        else {
            $app->flash('error', 'Sorry, this user name is already in use.');                
        }
    }
    else {
        $app->flash('error', 'Sorry, this user name is not valid.');
    }

    $app->redirect($app->urlFor('home'));
});

$app->get('/about', function() use ($app) {
    return $app->render('about.html');
});

/**
 * blog index page reads the directory specified in the posts.path and
 * loads the meta data for all the markdown files to display the blog index.
 */
$app->get('/:name/', function($name) use ($app) {
    $id = $app->config('blogDB')->getByName($name);
    if($id) {
        try {
            $app->config('dropboxStorage')->sync($name, $id);
            $rawPosts = $app->config('blogEngine')->getPosts($name);

            $currentPost = (isset($rawPosts[0]))? $rawPosts[0] : null;
            $currentPostTitle = (isset($rawPosts[0]))? $rawPosts[0]['meta']['title'] : null;
            $nextPost = $app->config('blogEngine')->getNext($name);

            if($nextPost) {
                $nextPost = $app->urlFor('blogPost', array('name'=>$name, 'slug'=>$nextPost));
            }

            return $app->render('single.html', array(
                'post' => $currentPost,
                'pageTitle' => $currentPostTitle,
                'blogUrl' => $app->urlFor('blogIndex', array('name'=>$name)),
                'nextPost' => $nextPost,
                'archiveUrl' => $app->urlFor('archive', array('name'=>$name))
            ));
        }
        catch (\Dropbox\Exception $e) {
            if($e->getCode() == 401 || $e->geCode() == 403) {
                $app->config('blogDB')->delete($id);
                $app->config('dropboxStorage')->delete($id);
                $app->config('blogEngine')->delete($name);
            }
            $app->getLog()->error($e->getCode() . ' : ' . $e->getMessage());
        }
    }
    $app->notFound();
})->name('blogIndex');

/**
 * show all posts from a blog
 *
 * @param string $name
 */
$app->get('/:name/archive/', function($name) use ($app) {
    $id = $app->config('blogDB')->getByName($name);
    if($id) {
        try {
            $app->config('dropboxStorage')->sync($name, $id);
            $rawPosts = $app->config('blogEngine')->getPosts($name);
            $posts = array_map(function($post) use ($app, $name) {
                $post['meta']['link'] = $app->urlFor('blogPost', array('name'=>$name, 'slug'=>$post['meta']['slug']));
                return $post;
            }, $rawPosts);
            return $app->render('archive.html', array(
                'posts'=> $posts
            ));
        } catch (\Dropbox\Exception $e) {
            if($e->getCode() == 401 || $e->geCode() == 403) {
                $app->config('blogDB')->delete($id);
                $app->config('dropboxStorage')->delete($id);
                $app->config('blogEngine')->delete($name);
            }
            $app->getLog()->error($e->getCode() . ' : ' . $e->getMessage());
        }
    }
    $app->notFound();
})->name('archive');

/**
 * get the posts from the blog engine and render the rss feed file with the
 * most recent posts
 *
 * @param string $name
 */
$app->get('/:name/feed', function($name) use ($app) {
    $id = $app->config('blogDB')->getByName($name);
    if($id) {
        try {
            $app->config('dropboxStorage')->sync($name, $id);
            $rawPosts = $app->config('blogEngine')->getPosts($name);
            $rawPosts = array_slice($rawPosts, 0, 15);
            $posts = array_map(function($post) use ($app, $name) {
                $post['meta']['link'] = $app->urlFor('blogPost', array('name'=>$name, 'slug'=>$post['meta']['slug']));
                return $post;
            }, $rawPosts);
            $app->contentType('application/rss+xml');
            return $app->render('feed.xml', array(
                'posts'=> $posts,
                'updated' => $posts[0]['meta']['date']
            ));
        } catch (\Dropbox\Exception $e) {
            if($e->getCode() == 401 || $e->geCode() == 403) {
                $app->config('blogDB')->delete($id);
                $app->config('dropboxStorage')->delete($id);
                $app->config('blogEngine')->delete($name);
            }
            $app->getLog()->error($e->getCode() . ' : ' . $e->getMessage());
        }
    }
    $app->notFound();
})->name('feed');

/**
 * check if the requested post file exist and load it to display the single post page
 *
 * @param string $name - blog name
 * @param string $slug - post slug
 */
$app->get('/:name/:slug', function($name, $slug) use ($app) {
    $id = $app->config('blogDB')->getByName($name);
    if($id) {
        try {
            $app->config('dropboxStorage')->sync($name, $id);
            if(is_array($post = $app->config('blogEngine')->getPost($name, $slug . '.md'))) {
                $title = (isset($post['meta']['title']))? $post['meta']['title'] : '';
                $nextPost = $app->config('blogEngine')->getNext($name);
                $prevPost = $app->config('blogEngine')->getPrev($name);

                if($nextPost) {
                    $nextPost = $app->urlFor('blogPost', array('name'=>$name, 'slug'=>$nextPost));
                }
                if($prevPost) {
                    $prevPost = $app->urlFor('blogPost', array('name'=>$name, 'slug'=>$prevPost));
                }

                return $app->render('single.html', array(
                    'post' => $post,
                    'pageTitle' => $title,
                    'blogUrl' => $app->urlFor('blogIndex', array('name'=>$name)),
                    'nextPost' => $nextPost,
                    'prevPost' => $prevPost,
                    'archiveUrl' => $app->urlFor('archive', array('name'=>$name))
                ));
            }
        } catch (\Dropbox\Exception $e) {
            if($e->getCode() == 401 || $e->geCode() == 403) {
                $app->config('blogDB')->delete($id);
                $app->config('dropboxStorage')->delete($id);
                $app->config('blogEngine')->delete($name);
            }
            $app->getLog()->error($e->getCode() . ' : ' . $e->getMessage());
        }
    }
    $app->notFound();
})->name('blogPost');

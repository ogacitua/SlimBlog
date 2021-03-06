<?php
$app->group('/admin', function () use ($app, $settings, $isLogged, $authenticate) {
    $app->get('/login/', $isLogged($app, $settings), function() use ($app) {
        $flash = $app->view()->getData('flash');
        $error = isset($flash['error']) ? $flash['error'] : '';

        $app->render('login.html', array('error' => $error));
    });

    $app->post('/login', function() use ($app, $settings) {
        $username = $app->request->post('form-username');
        $password = hash('sha512', $app->request->post('form-password'));
        $user = Users::whereRaw('username = ? AND password = ?', array($username, $password))->get();

        if ($user->count() != 0) {
            $_SESSION['user'] = $username;
            $app->redirect($settings->base_url . '/admin');
        } else {
            $app->flash('error', 1);
            $app->redirect($settings->base_url . '/admin/login');
        }
    });

    $app->get('/logout/', $authenticate($app, $settings), function() use ($app, $settings) {
        unset($_SESSION['user']);
        $app->view()->setData('user', null);
        $app->redirect($settings->base_url);
    });

    $app->get('/', $authenticate($app, $settings), function() use ($app) {
        $posts = Posts::orderBy('creation', 'desc')->get();
        $arr = array();
        foreach ($posts as $post) {
            $post['author'] = Users::get_author($post['user_id']);
            $post['date'] = date('d-m-Y H:i', $post['creation']);
            $post['url'] = $app->request->getUrl() . $app->request->getPath() . 'post/' . $post['id'];
            $arr[] = $post;
        }
        $app->render('a_posts.html', array('posts' => $arr));
    });

    $app->get('/posts/new/', $authenticate($app, $settings), function() use ($app) {
        $flash = $app->view()->getData('flash');
        $error = isset($flash['error']) ? $flash['error'] : '';

        $app->render('a_post_new.html', array('error' => $error));
    });

    $app->post('/posts/new', $authenticate($app, $settings), function() use ($app, $settings) {
        $title = $app->request->post('title');
        $text = $app->request->post('markdown');
        $redirect = $app->request->post('redirect');

        if ($title == "") {
            $app->flash('error', 1);
            $app->redirect($settings->base_url . '/admin/posts/new');
        }
        if ($text == "") {
            $app->flash('error', 2);
            $app->redirect($settings->base_url . '/admin/posts/new');
        }

        $date = time();
        $author = Users::get_id($_SESSION['user']);

        Posts::insert(array('title' => $title, 'creation' => $date, 'text' => $text, 'user_id' => $author));
        $app->render('success.html', array('redirect' => $redirect));
    });

    $app->post('/markdown/ajax', $authenticate($app, $settings), function() use ($app) {
        if ($app->request->post('markdown') !== null) {
            echo $app->markdown->parse($app->request->post('markdown'));
        }
    });

    $app->get('/posts/edit/:id', $authenticate($app, $settings), function($id) use ($app) {
        $post = Posts::where('id', '=', $id)->first();

        if($post){
            $title = $post->title;
            $text = $post->text;
            $postId = $id;

            $flash = $app->view()->getData('flash');
            $error = isset($flash['error']) ? $flash['error'] : '';

            $app->render('a_post_edit.html', array('id' => $postId, 'title' => $title, 'text' => $text, 'error' => $error));
        }
        else{
            $app->render('404_post.html');
        }
    })->conditions(array('id' => '\d+'));

    $app->post('/posts/edit/:id', $authenticate($app, $settings), function($id) use ($app, $settings) {
        $title = $app->request->post('title');
        $text = $app->request->post('markdown');

        $post = Posts::where('id', '=', $id)->first();

        if($post){
            if ($title == "") {
                $app->flash('error', 1);
                $app->redirect($settings->base_url . '/admin/posts/edit/' . $id);
            }
            if ($text == "") {
                $app->flash('error', 2);
                $app->redirect($settings->base_url . '/admin/posts/edit/' . $id);
            }

            $redirect = $settings->base_url . '/admin';

            $post->update(array('title' => $title, 'text' => $text));
            $app->render('success.html', array('redirect' => $redirect));
        }
        else {
            $app->render('404_post.html');
        }
    })->conditions(array('id' => '\d+'));

    $app->get('/posts/delete/:id', $authenticate($app, $settings), function($id) use ($app) {
        $post = Posts::where('id', '=', $id)->first();

        if($post){
            $app->render('a_post_delete.html', array('post_id' => $id));
        }
        else {
            $app->render('404_post.html');
        }

    })->conditions(array('id' => '\d+'));

    $app->delete('/posts/delete/:id', $authenticate($app, $settings), function($id) use ($app, $settings) {
        $post = Posts::where('id', '=', $id)->first();

        if($post){
            Posts::destroy($id);
            $redirect = $settings->base_url . '/admin';
            $app->render('success.html', array('redirect' => $redirect));
        }
        else {
            $app->render('404_post.html');
        }
    })->conditions(array('id' => '\d+'));

    $app->get('/settings/', $authenticate($app, $settings), function() use ($app) {
        $flash = $app->view()->getData('flash');
        $error = isset($flash['error']) ? $flash['error'] : '';

        $paths = glob(TEMPLATEDIR . '*' , GLOB_ONLYDIR);
        $dirs = array();
        foreach($paths as $path) {
            $a = explode(DS, $path);
            $dirs[] = end($a);
        }

        $l = glob(LANGUAGEDIR . '*.php');
        $langs = array();
        foreach($l as $lang) {
            $a = explode('.', $lang);
            $b = explode(DS, $a[0]);
            $langs[] = end($b);
        }

        $app->render('a_settings.html', array('error' => $error, 'dirs' => $dirs, 'langs' => $langs));
    });

    $app->post('/settings/update', function() use ($app, $settings) {
        $title = $app->request->post('title');
        $post_per_page = (int)$app->request->post('post_per_page');
        $template = $app->request->post('template');
        $truncate = $app->request->post('truncate') == 'on' ? 'true' : 'false';
        $language = $app->request->post('language');

        if($title == "") {
            $app->flash('error', 1);
            $app->redirect($settings->base_url . '/admin/settings');
        }
        if($post_per_page == '') {
            $app->flash('error', 2);
            $app->redirect($settings->base_url . '/admin/settings');
        }
        if($template == '') {
            $app->flash('error', 3);
            $app->redirect($settings->base_url . '/admin/settings');
        }
        if($language == '') {
            $app->flash('error', 4);
            $app->redirect($settings->base_url . '/admin/settings');
        }

        $redirect = $settings->base_url . '/admin/settings';

        Settings::where('id', '=', 1)->update(array('title' => $title, 'template' => $template, 'post_per_page' => $post_per_page, 'truncate' => $truncate, 'language' => $language));
        $app->render('success.html', array('redirect' => $redirect));
    });

    $app->get('/users/', $authenticate($app, $settings), function() use ($app) {
        $users = Users::orderBy('created_at', 'asc')->get();
        $app->render('a_users.html', array('users' => $users));
    });

    $app->get('/users/edit/:id', $authenticate($app, $settings), function($id) use ($app) {
        $flash = $app->view()->getData('flash');
        $error = isset($flash['error']) ? $flash['error'] : '';

        $u = Users::where('id', '=', $id)->first();
        $app->render('a_user_edit.html', array('u' => $u, 'error' => $error));
    })->conditions(array('id' => '\d+'));

    $app->post('/users/edit/:id', $authenticate($app, $settings), function($id) use ($app, $settings) {
        $username = $app->request->post('username');
        $password = hash('sha512', $app->request->post('password'));
        $email = $app->request->post('email');

        if($username == "") {
            $app->flash('error', 1);
            $app->redirect($settings->base_url . '/admin/users/new');
        }
        if($email == "" OR !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $app->flash('error', 2);
            $app->redirect($settings->base_url . '/admin/users/new');
        }

        $redirect = $settings->base_url . '/admin/users';

        Users::where('id', '=', $id)->update(array('username' => $username, 'password' => $password, 'email' => $email));
        $app->render('success.html', array('redirect' => $redirect));
    })->conditions(array('id' => '\d+'));

    $app->get('/users/delete/:id', $authenticate($app, $settings), function($id) use ($app) {
        $app->render('a_user_delete.html', array('user_id' => $id));
    })->conditions(array('id' => '\d+'));

    $app->delete('/users/delete/:id', $authenticate($app, $settings), function($id) use ($app, $settings) {
        Users::destroy($id);
        $redirect = $settings->base_url . '/admin/users';
        $app->render('success.html', array('redirect' => $redirect));
    })->conditions(array('id' => '\d+'));

    $app->get('/users/new/', $authenticate($app, $settings), function() use ($app) {
        $flash = $app->view()->getData('flash');
        $error = isset($flash['error']) ? $flash['error'] : '';

        $app->render('a_user_new.html', array('error' => $error));
    });

    $app->post('/users/new', $authenticate($app, $settings), function() use ($app, $settings) {
        $username = $app->request->post('username');
        $password = hash('sha512', $app->request->post('password'));
        $email = $app->request->post('email');
        $created_at = date('Y-m-d H:i:s');

        if($username == "") {
            $app->flash('error', 1);
            $app->redirect($settings->base_url . '/admin/users/new');
        }
        if($password == "") {
            $app->flash('error', 2);
            $app->redirect($settings->base_url . '/admin/users/new');
        }
        if($email == "" OR !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $app->flash('error', 3);
            $app->redirect($settings->base_url . '/admin/users/new');
        }

        $redirect = $settings->base_url . '/admin/users';

        Users::insert(array('username' => $username, 'password' => $password, 'email' => $email, 'created_at' => $created_at));
        $app->render('success.html', array('redirect' => $redirect));
    });

    $app->get('/posts/activate/:id', $authenticate($app, $settings), function($id) use ($app, $settings) {
        $post = Posts::where('id', '=', $id);

        if($post){
            $redirect = $settings->base_url . '/admin';

            $post->update(array('active' => 'true'));
            $app->render('success.html', array('redirect' => $redirect));
        }
        else {
            $app->render('404_post.html');
        }
    })->conditions(array('id' => '\d+'));

    $app->get('/posts/deactivate/:id', $authenticate($app, $settings), function($id) use ($app, $settings) {
        $post = Posts::where('id', '=', $id);

        if($post){
            $redirect = $settings->base_url . '/admin';

            $post->update(array('active' => 'false'));
            $app->render('success.html', array('redirect' => $redirect));
        }
        else {
            $app->render('404_post.html');
        }
    })->conditions(array('id' => '\d+'));
});
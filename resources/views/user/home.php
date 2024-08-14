<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Home</title>
        <meta name="description" content="Home">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl">Welcome</h1>
            
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>
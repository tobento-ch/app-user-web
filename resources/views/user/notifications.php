<?php
$form = $view->form();
$table = $view->table(name: 'notifications');

$table->row([
    'date' => $view->trans('Date'),
    'message' => $view->trans('Message'),
    'actions' => $view->trans('Actions'),
])->heading();

foreach($notifications as $notification) {
    
    $actions = '<div class="buttons spaced">';
        
    foreach($notification->actions() as $action) {
        $actions .= '<a class="button primary text-xs" href="'.$view->esc($action->url()).'">'.$view->esc($action->text()).'</a>';
    }
    
    $actions .= $form->form(['method' => 'PATCH', 'action' => $view->routeUrl('notifications.dismiss')]);
    $actions .= $form->input(name: 'id', type: 'hidden', value: $notification->id());
    $actions .= $form->button(text: $view->trans('dismiss'), attributes: ['class' => 'button text-xs']);
    $actions .= $form->close();
    
    $actions .= '</div>';
    
    $table->row([
        'date' => $view->dateTime($notification->get('created_at')),
        'message' => $notification->message(),
        'actions' => $actions,
    ])->html('actions')->attributes(['class' => 'top']);
}
?>
<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Notifications') ?></title>
        <meta name="description" content="<?= $view->etrans('Notifications') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
        
        <?php
        $view->asset('assets/css/table.css');
        ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Notifications') ?></h1>
            
            <div class="form-fields p-m mt-s">
                <?php if ($hasNotifications) { ?>
                    <?= $table ?>
                    <div class="mt-m">
                        <?php $form = $view->form(); ?>
                        <?= $form->form(['action' => $view->routeUrl('notifications.dismiss.all')]) ?>
                        <?= $form->button(text: $view->trans('dismiss all'), attributes: ['class' => 'button text-xs primary']) ?>
                        <?= $form->close() ?>
                    </div>
                <?php } else { ?>
                    <p><?= $view->etrans('You have no notifications.') ?></p>
                <?php } ?>
            </div>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>
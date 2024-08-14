<!DOCTYPE html>
<html lang="<?= $view->esc($view->get('htmlLang', 'en')) ?>">
	
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $view->etrans('Profile Settings') ?></title>
        <meta name="description" content="<?= $view->etrans('Profile Settings') ?>">
        
        <?= $view->render('inc/head') ?>
        <?= $view->assets()->render() ?>
    </head>
    
    <body<?= $view->tagAttributes('body')->add('class', 'page')->render() ?>>

        <?= $view->render('inc/header') ?>
        <?= $view->render('inc/nav') ?>

        
        <main class="page-main">

            <?= $view->render('inc.breadcrumb') ?>
            <?= $view->render('inc.messages') ?>

            <h1 class="title text-xl"><?= $view->etrans('Profile Settings') ?></h1>
            
            <section class="form-fields p-m mt-s">
                <h2 class="text-m mb-s"><?= $view->etrans('General') ?></h2>

                <?php $form = $view->form(); ?>
                <?= $form->form([
                    'action' => $view->routeUrl('profile.settings.update'),
                    'method' => 'PATCH',
                    'name' => 'update',
                ]) ?>

                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Preferred Language'),
                            for: 'locale',
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->select(
                            name: 'locale',
                            items: $languages->column('name', 'locale'),
                            selected: [$user->locale()],
                        ) ?>
                    </div>
                </div>
                
                <h2 class="text-m mt-l mb-s"><?= $view->etrans('Notifications') ?></h2>
                
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Newsletter'),
                            for: 'newsletter',
                        ) ?>
                    </div>
                    <div class="field-body">                
                        <div class="cols">
                            <div>
                                <?= $form->input(
                                    name: 'newsletter',
                                    type: 'checkbox',
                                    value: '1',
                                    selected: [(string)$user->newsletter()],
                                ) ?>
                            </div>
                            <div class="col ml-xs">
                                <label for="newsletter" class="input-value"><?= $view->etrans('Yes, I would like to subscribe to the newsletter.') ?></label>
                            </div>
                        </div>           
                    </div>
                </div>
                
                <?php if ($channels->count() > 0) { ?>
                <div class="field">
                    <div class="field-label">
                        <?= $form->label(
                            text: $view->trans('Preferred Channels'),
                        ) ?>
                    </div>
                    <div class="field-body">
                        <?= $form->checkboxes(
                            name: 'settings.preferred_notification_channels',
                            items: $channels,
                            selected: $user->setting('preferred_notification_channels', []),
                            wrapClass: 'wrap-v',
                        ) ?>
                    </div>
                </div>
                <?php } ?>
                
                <div class="buttons spaced">
                    <?= $form->button(text: $view->trans('Save'), attributes: ['class' => 'button primary text-xs']) ?>
                </div>

                <?= $form->close() ?>
            </section>
        </main>

        <?= $view->render('inc/footer') ?>
    </body>
</html>
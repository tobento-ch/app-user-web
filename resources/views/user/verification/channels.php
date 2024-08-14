<?php
$view->asset('assets/css/table.css');

$table = $view->table(name: 'verifications');

$table->row([
    'channel' => $view->trans('Channel'),
    'verified' => $view->trans('Verified At'),
    'actions' => $view->trans('Actions'),
])->heading();

if (!empty($user->email()) && $channels->has('mail')) {
    $table->row([
        'channel' => $user->email(),
        'verified' => $user->isVerified(['email']) ? $user->getVerifiedAt('email') : '-',
        'actions' => $user->isVerified(['email'])
            ? ''
            : '<a href="'.$view->routeUrl('verification.channel', ['channel' => 'email']).'">'.$view->etrans('verify').'</a>',
    ])->html('actions');
}

if (!empty($user->smartphone()) && $channels->has('sms')) {
    $table->row([
        'channel' => $user->smartphone(),
        'verified' => $user->isVerified(['smartphone']) ? $user->getVerifiedAt('smartphone') : '-',
        'actions' => $user->isVerified(['smartphone'])
            ? ''
            : '<a href="'.$view->routeUrl('verification.channel', ['channel' => 'smartphone']).'">'.$view->etrans('verify').'</a>',
    ])->html('actions');
}

if (!empty($channelColumns)) {
    $table = $table->withColumns($channelColumns);
}
?>
<div><?= $table ?></div>
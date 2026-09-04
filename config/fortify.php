<?php

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,

    // O login administrativo provisório sempre direciona para a rota protegida.
    'home' => '/admin',

    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => [
        'login' => 'login',
    ],
    'views' => true,

    // Cadastro, recuperação de senha, verificação, 2FA e passkeys pertencem à Fase 3.
    'features' => [],
];

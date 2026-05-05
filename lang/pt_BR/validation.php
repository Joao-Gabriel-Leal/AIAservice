<?php

return [
    'confirmed' => 'A confirmacao de :attribute nao confere.',
    'current_password' => 'A senha atual informada esta incorreta.',
    'required' => 'O campo :attribute e obrigatorio.',
    'string' => 'O campo :attribute deve ser um texto.',

    'min' => [
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],

    'password' => [
        'letters' => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed' => 'O campo :attribute deve conter pelo menos uma letra maiuscula e uma minuscula.',
        'numbers' => 'O campo :attribute deve conter pelo menos um numero.',
        'symbols' => 'O campo :attribute deve conter pelo menos um simbolo.',
        'uncompromised' => 'Essa :attribute apareceu em vazamentos de dados. Escolha outra.',
    ],

    'attributes' => [
        'current_password' => 'senha atual',
        'password' => 'nova senha',
        'password_confirmation' => 'confirmacao da nova senha',
    ],
];

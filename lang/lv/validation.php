<?php

/*
 * Latvian validation messages for the rules this app uses. Any rule not listed
 * here falls back to the English message.
 */
return [
    'array' => 'Laukam :attribute jābūt sarakstam.',
    'confirmed' => 'Lauka :attribute apstiprinājums nesakrīt.',
    'current_password' => 'Parole nav pareiza.',
    'distinct' => 'Laukā :attribute ir atkārtota vērtība.',
    'email' => 'Laukam :attribute jābūt derīgai e-pasta adresei.',
    'exists' => 'Izvēlētā :attribute vērtība nav derīga.',
    'in' => 'Izvēlētā :attribute vērtība nav derīga.',
    'integer' => 'Laukam :attribute jābūt veselam skaitlim.',
    'lowercase' => 'Laukam :attribute jābūt rakstītam ar mazajiem burtiem.',
    'max' => [
        'array' => 'Laukā :attribute var būt ne vairāk kā :max vienumi.',
        'numeric' => 'Lauks :attribute nedrīkst būt lielāks par :max.',
        'string' => 'Lauks :attribute nedrīkst būt garāks par :max rakstzīmēm.',
    ],
    'min' => [
        'array' => 'Laukā :attribute jābūt vismaz :min vienumiem.',
        'numeric' => 'Laukam :attribute jābūt vismaz :min.',
        'string' => 'Laukam :attribute jābūt vismaz :min rakstzīmes garam.',
    ],
    'password' => [
        'letters' => 'Parolē jābūt vismaz vienam burtam.',
        'mixed' => 'Parolē jābūt vismaz vienam lielajam un vienam mazajam burtam.',
        'numbers' => 'Parolē jābūt vismaz vienam ciparam.',
        'symbols' => 'Parolē jābūt vismaz vienam simbolam.',
        'uncompromised' => 'Šī parole ir parādījusies datu noplūdē. Lūdzu, izvēlies citu paroli.',
    ],
    'required' => 'Lauks :attribute ir obligāts.',
    'string' => 'Laukam :attribute jābūt tekstam.',
    'unique' => 'Šāda :attribute vērtība jau ir aizņemta.',

    'attributes' => [
        'current_password' => 'pašreizējā parole',
        'email' => 'e-pasts',
        'name' => 'nosaukums',
        'password' => 'parole',
        'product_ids' => 'produkti',
        'quantity' => 'daudzums',
        'role' => 'loma',
        'shopping_list_id' => 'saraksts',
    ],
];

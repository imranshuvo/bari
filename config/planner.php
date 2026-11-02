<?php

return [

    /*
    | When false (the default), nobody can sign up on their own. People join
    | through an invitation link from a project owner, or are created with
    | `php artisan user:create`.
    */
    'open_registration' => (bool) env('OPEN_REGISTRATION', false),

];

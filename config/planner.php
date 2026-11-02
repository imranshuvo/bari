<?php

return [

    /*
    | When false (the default), nobody can sign up on their own. People join
    | through an invitation link from a project owner, or are created with
    | `php artisan user:create`.
    */
    'open_registration' => (bool) env('OPEN_REGISTRATION', false),

    /*
    | Country code added to local phone numbers typed with a leading 0
    | (01712345678 → +8801712345678).
    */
    'phone_country_code' => env('PHONE_COUNTRY_CODE', '880'),

];

<?php

return [
    /*
     * Блокировать доступ к /api/v1/users для пользователей без статуса verify.
     * На проде включается вручную через FEATURE_USER_STATUS_GATE=true.
     */
    'user_status_gate' => (bool) env('FEATURE_USER_STATUS_GATE', false),
];

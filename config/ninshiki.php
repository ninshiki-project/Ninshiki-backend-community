<?php

return [

    /**
     *  =============================================
     *  ---------- User Domain Filter ---------------
     *
     * This is to make sure that there is no other Zoho user can log in and access
     * the application that is not under allowed domain.
     *
     *  This param accepts array or string
     *  Ex.
     * ['mydomain.com','myotherdomain']
     *
     * If the ALLOWED_EMAIL_DOMAIN is null, then all the TLD domains will be passed in the filter
     * ==============================================
     */
    'allowed_email_domain' => env('ALLOWED_EMAIL_DOMAIN', null),

];

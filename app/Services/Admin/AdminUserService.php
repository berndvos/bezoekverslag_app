<?php

namespace App\Services\Admin;

use App\Controllers\AuthController;
use PDO;

class AdminUserService
{
    public function createUser(PDO , array ): AdminServiceResponse
    {
         = null;

         = ->validateCreateInput();
        if (!->success) {
             = ;
        } else {
             = ->data['fullname'];
             = ->data['email'];
             = ->data['role'];

            if (->emailExists(, )) {
                 = new AdminServiceResponse(false, 'Dit e-mailadres is al in gebruik.', 'danger');
            } else {
                 = ->insertNewUser(, , , );
                if ( === 0) {
                     = new AdminServiceResponse(false, 'Kon gebruiker niet opslaan.', 'danger');
                } else {
                     = ->findUserById(, );
                    [, ] = ->buildCreateUserMessage(, );

                    if (function_exists('log_action')) {
                        log_action('user_created', "Gebruiker '{}' aangemaakt.");
                    }

                     = new AdminServiceResponse(true, , );
                }
            }
        }

        return  ?? new AdminServiceResponse(false, 'Onbekende fout bij aanmaken gebruiker.', 'danger');
    }

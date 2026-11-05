<?php

namespace App\Exceptions;

use Exception;

class CustomerLinkException extends Exception
{
    public static function userAlreadyLinked(): self
    {
        return new self(__('messages.user_already_linked_to_customer'));
    }

    public static function customerAlreadyLinked(): self
    {
        return new self(__('messages.customer_already_linked_to_user'));
    }
}

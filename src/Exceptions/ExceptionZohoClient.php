<?php

namespace Wabel\Zoho\CRM\Exceptions;


class ExceptionZohoClient
{
    const EXCEPTION_CODE_NO__CONTENT = "no_content";

    static public function exceptionCodeFormat($errorCode)
    {
        return str_replace([' '], ['_'], strtolower($errorCode));
    }
}
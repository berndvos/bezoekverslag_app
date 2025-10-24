<?php

namespace App\Services\Admin;

class AdminServiceResponse
{
    public function __construct(
        public bool $success,
        public string $message = '',
        public string $type = 'info',
        public array $data = []
    ) {
    }
}

<?php

namespace App\Contracts;

use App\Enums\ServerStatus;

interface HealthCheckable
{
    public function check(): ServerStatus;
    public function getName(): string;
}

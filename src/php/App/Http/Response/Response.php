<?php

declare(strict_types=1);

namespace App\Http\Response;

interface Response
{
    public function send(): void;
}

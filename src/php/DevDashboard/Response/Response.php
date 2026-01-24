<?php

declare(strict_types=1);

namespace DevDashboard\Response;

interface Response
{
    public function send(): void;
}

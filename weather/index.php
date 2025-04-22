<?php

declare(strict_types=1);

namespace server;

usleep(random_int(2_000_000, 5_000_000));

header('Content-Type: application/json');

echo json_encode([
    'temperature' => random_int(16, 36),
    'humidity' => random_int(30, 100),
]);

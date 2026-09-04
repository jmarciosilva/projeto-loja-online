<?php

// Lightweight liveness endpoint. Lives in the repo because compose bind-mounts
// ./ over /app at runtime, which would shadow anything created at build time.
http_response_code(200);
header('Content-Type: text/plain');
echo 'OK';

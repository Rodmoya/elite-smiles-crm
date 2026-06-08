<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
echo 'This intake link is not active. Please contact Elite Smiles.';

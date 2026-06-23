<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Cloudinary\Cloudinary;

$cloudinary = new Cloudinary([
  'cloud' => [
    'cloud_name' => getenv('CLOUD_NAME'),
    'api_key'    => getenv('API_KEY'),
    'api_secret' => getenv('API_SECRET')
  ]
]);

?>